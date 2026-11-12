import groovy.json.JsonOutput
import groovy.json.JsonSlurper
import org.apache.jmeter.protocol.http.control.Cookie

def config = vars.getObject('athena.config')
def user = vars.getObject('athena.user')
def scenario = user.scenario ?: config
def id = Parameters
def op = id == 'session' ? [path: scenario.dashboard, method:'GET'] : scenario.operations.find { it.id == id }
if (user.workspace) {
    def role = user.workspace
    if (id == 'session') {
        sampler.setName('SETUP | Verify authenticated workspace | ' + role)
    } else {
        def record = user.recordSets[(vars.getIteration() - 1 + ctx.getThreadNum()) % user.recordSets.size()]
        record.each { k,v -> vars.put(k, String.valueOf(v)) }
        vars.put('project_key', op.records?.any { it in ['topic_id', 'draft_id', 'report_id'] } ? String.valueOf(record.topic_id) : '')
    }
}
if (id != 'session') {
    def prefix = vars.getIteration() <= config.warmupLoops ? 'WARMUP' : 'PERF'
    sampler.setName(prefix + ' | ' + id + ' | ' + op.name + (user.workspace ? ' | ' + user.workspace : ''))
}
def expand = { String value -> value.replaceAll(/\$\{([a-zA-Z0-9_]+)\}/) { full, key ->
    def replacement = vars.get(key)
    if (replacement == null || replacement.isEmpty()) throw new IllegalStateException('Missing fixture variable: ' + key)
    java.util.regex.Matcher.quoteReplacement(replacement)
} }
def base = new URI(config.baseUrl)
if (op.component && !vars.get('athena.livewire_uri')) throw new IllegalStateException('Livewire update URL was missing from the page.')
def target = op.component ? base.resolve(vars.get('athena.livewire_uri')) : new URI(config.baseUrl + expand(op.path))
if (target.host != base.host || target.scheme != base.scheme || target.port != base.port) throw new IllegalStateException('Cross-origin requests are not allowed.')
sampler.setProtocol(target.scheme)
sampler.setDomain(target.host)
sampler.setPort(target.port == -1 ? (target.scheme == 'https' ? 443 : 80) : target.port)
sampler.setPath(target.rawPath + (target.rawQuery ? '?' + target.rawQuery : ''))
sampler.setMethod(op.method)
sampler.setFollowRedirects(false)
sampler.setAutoRedirects(false)
sampler.getArguments().removeAllArguments()
sampler.getHeaderManager().removeHeaderNamed('Content-Type')
sampler.getHeaderManager().removeHeaderNamed('X-CSRF-TOKEN')
sampler.getHeaderManager().removeHeaderNamed('X-Livewire')
sampler.getHeaderManager().removeHeaderNamed('Accept')
sampler.getHeaderManager().removeHeaderNamed('Referer')
if (op.referer) sampler.getHeaderManager().add(new org.apache.jmeter.protocol.http.control.Header('Referer', config.baseUrl + expand(op.referer)))
sampler.getHeaderManager().add(new org.apache.jmeter.protocol.http.control.Header('Accept', op.json || op.payload || op.component ? 'application/json' : 'text/html,application/xhtml+xml'))
if (!vars.get('athena.cookies_loaded')) {
    def state = new JsonSlurper().parse(new File(user.sessionFile))
    def manager = sampler.getCookieManager()
    state.cookies.each { c ->
        def host = c.domain.replaceFirst(/^\./, '')
        if (target.host == host || target.host.endsWith('.' + host)) {
            manager.add(new Cookie(c.name, c.value, c.domain, c.path ?: '/', c.secure ?: false, 0))
        }
    }
    vars.put('athena.cookies_loaded', 'true')
}
if (op.method != 'GET') {
    def csrf = vars.get('athena.csrf')
    if (!csrf) throw new IllegalStateException('No current CSRF token. The authenticated setup request must succeed first.')
    def data
    if (op.component) {
        def snapshot = vars.getObject('athena.snapshots')[op.component]
        if (!snapshot) throw new IllegalStateException('Required Livewire snapshot missing: ' + op.component)
        data = [_token:csrf, components:[[snapshot:snapshot, updates:[:], calls:[[path:'', method:'$refresh', params:[]]]]]]
        sampler.getHeaderManager().add(new org.apache.jmeter.protocol.http.control.Header('X-Livewire','true'))
    } else {
        data = new JsonSlurper().parseText(expand(JsonOutput.toJson(user.payloads[id])))
        if (op.budgetToken) {
            if (!vars.get('budget_token')) throw new IllegalStateException('Missing current budget concurrency token.')
            data.budget_token = vars.get('budget_token')
        }
    }
    sampler.getHeaderManager().add(new org.apache.jmeter.protocol.http.control.Header('X-CSRF-TOKEN', csrf))
    sampler.getHeaderManager().add(new org.apache.jmeter.protocol.http.control.Header('Content-Type','application/json'))
    sampler.setPostBodyRaw(true)
    sampler.addNonEncodedArgument('', JsonOutput.toJson(data), '')
} else {
    sampler.setPostBodyRaw(false)
}
