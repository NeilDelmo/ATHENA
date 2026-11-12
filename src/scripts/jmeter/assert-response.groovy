import groovy.json.JsonSlurper
import org.jsoup.Jsoup

def config = vars.getObject('athena.config')
def user = vars.getObject('athena.user')
def scenario = user.scenario ?: config
def id = Parameters
def op = id == 'session' ? [status:[200], session:true] : scenario.operations.find { it.id == id }
if (!op.session && config.warmupSeconds && prev.getStartTime() < org.apache.jmeter.threads.JMeterContextService.getTestStartTime() + config.warmupSeconds * 1000L) {
    prev.setSampleLabel(prev.getSampleLabel().replaceFirst(/^PERF/, 'WARMUP'))
}
if (!op.session && config.loadModel == 'shared-account' && config.measurementSeconds &&
    prev.getStartTime() >= org.apache.jmeter.threads.JMeterContextService.getTestStartTime() + (config.warmupSeconds + config.measurementSeconds) * 1000L) {
    prev.setSampleLabel(prev.getSampleLabel().replaceFirst(/^PERF/, 'COOLDOWN'))
}
def fail = { String message ->
    AssertionResult.setFailure(true)
    AssertionResult.setFailureMessage(message)
    prev.setSuccessful(false)
    if (op.session || !config.warmupSeconds || prev.getSampleLabel().startsWith('WARMUP | ')) prev.setStopThread(true)
}
try {
    if (!op.status.contains(prev.getResponseCode().isInteger() ? prev.getResponseCode().toInteger() : -1)) {
        fail('Unexpected HTTP status: ' + prev.getResponseCode() + '. Check authentication, permissions, validation and rate limits.')
        return
    }
    def headers = prev.getResponseHeaders()
    def locationLine = headers.readLines().find { it.toLowerCase().startsWith('location:') }
    if (locationLine) {
        def location = new URI(config.baseUrl).resolve(locationLine.substring(9).trim())
        def base = new URI(config.baseUrl)
        if (location.host != base.host || location.scheme != base.scheme || location.port != base.port || location.path ==~ /.*\/(login|choose-workspace|select-role)\/?$/) {
            fail('Unexpected authentication/workspace or cross-origin redirect.'); return
        }
        def expected = op.location?.replace('\u0024{topic_id}', vars.get('topic_id') ?: '')
        if (expected && !(location.path ==~ (new URI(config.baseUrl).path + expected))) {
            fail('Redirect does not match the successful operation destination.'); return
        }
    } else if (op.location) {
        fail('Expected successful creation redirect is missing.'); return
    }
    if (op.binary) {
        if (prev.getResponseData().length == 0 || (headers =~ /(?im)^content-type:\s*text\/html/).find()) fail('Expected a nonempty document, not an HTML page.')
        return
    }
    def text = prev.getResponseDataAsString()
    if (op.json || op.component || (op.payload && prev.getResponseCode() == '200')) {
        def data = new JsonSlurper().parseText(text)
        if (data instanceof Map && (data.error || data.errors || data.success == false)) { fail('Application reported a JSON error.'); return }
        if (op.component && (!(data.components instanceof List) || data.components.isEmpty())) { fail('Missing Livewire component response.'); return }
        (data instanceof Map ? data.components : null)?.each { c ->
            def snapshot = new JsonSlurper().parseText(c.snapshot)
            vars.getObject('athena.snapshots')[snapshot.memo.name] = c.snapshot
            if (c.effects?.errors) fail('Livewire reported validation errors.')
        }
    } else if (prev.getResponseCode() == '200') {
        def document = Jsoup.parse(text)
        def athenaInlineConfigMatch = text =~ /(?s)window\.livewireScriptConfig\s*=\s*(\{.*?\})\s*;/
        if (athenaInlineConfigMatch.find()) {
            def inlineUri = new groovy.json.JsonSlurper().parseText(athenaInlineConfigMatch.group(1)).uri
            if (inlineUri) vars.put('athena.livewire_uri', inlineUri.toString())
        }
        def deployedUpdateUri = document.select('script[data-update-uri]').first()?.attr('data-update-uri')
        if (deployedUpdateUri) vars.put('athena.livewire_uri', deployedUpdateUri)
        if (document.select('a[href$="/auth/google"]').size() > 0 || document.select('form[action$="/login"]').size() > 0) { fail('A sign-in page was returned instead of the module.'); return }
        if (op.session && !text.contains(scenario.workspaceMarker)) { fail('The saved session did not open the expected workspace.'); return }
        if ((op.session || user.workspace) && document.select('body').first()?.attr('data-active-workspace') != (user.workspace ?: config.workspace)) { fail('The authenticated account is using a different workspace.'); return }
        if (user.actorId && document.select('body').first()?.attr('data-auth-user-id') != String.valueOf(user.actorId)) { fail('The authenticated account does not match the assigned test user.'); return }
        def token = document.select('meta[name=csrf-token]').first()?.attr('content')
        if (token) vars.put('athena.csrf', token)
        if (op.session && !token) { fail('The authenticated response did not contain a CSRF token.'); return }
        document.getElementsByAttribute('wire:snapshot').each { element ->
            def raw = element.attr('wire:snapshot')
            def snapshot = new JsonSlurper().parseText(raw)
            vars.getObject('athena.snapshots')[snapshot.memo.name] = raw
        }
        def budgetToken = document.select('input[name=budget_token]').first()?.attr('value')
        if (budgetToken) vars.put('budget_token', budgetToken)
    }
} catch (Exception ignored) {
    fail('Response structure did not match the expected operation. No response content has been saved.')
}
