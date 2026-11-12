import groovy.json.JsonSlurper
import groovy.json.JsonSlurperClassic

try {
    def file = new File(props.getProperty('athena.config', ''))
    if (!file.isFile()) throw new IllegalStateException('Prepare a run first and load its run.properties with -q.')
    def config
    if (props.getProperty('athena.shared_account') == 'true') {
        // Parse the shared workload once instead of competing for CPU in every virtual user.
        synchronized (props) {
            def key = 'athena.shared.config.' + file.absolutePath
            config = props.get(key)
            if (config == null) {
                config = new JsonSlurperClassic().parse(file)
                props.put(key, config)
            }
        }
    } else {
        config = new JsonSlurper().parse(file)
    }
    if (config.users.size() < ctx.getThreadGroup().getNumThreads()) throw new IllegalStateException('One independent saved session is required per virtual user.')
    def user = config.users[ctx.getThreadNum()]
    def scenario = user.scenario ?: config
    if (config.operations.any { it.write } && (!config.allowWrites || config.loops != 1 || config.durationSeconds != 0)) {
        throw new IllegalStateException('Write runs require explicit enablement, one iteration and no duration loop.')
    }
    if (config.operations.any { it.external } && !config.allowExternal) throw new IllegalStateException('External requests were not enabled.')
    vars.putObject('athena.config', config)
    vars.putObject('athena.user', user)
    vars.putObject('athena.snapshots', [:])
    scenario.operations.each { vars.put('enabled_' + it.id, 'true') }
    vars.put('run_id', config.runId)
    vars.put('user_index', String.valueOf(ctx.getThreadNum() + 1))
    vars.put('workspace', user.workspace ?: config.workspace)
    vars.put('actor_index', vars.get('user_index'))
    if (user.workspace) SampleResult.setSampleLabel('SETUP | Load configuration | ' + user.workspace)
    user.records.each { k,v -> vars.put(k, String.valueOf(v)) }
    SampleResult.setResponseCode('200')
    SampleResult.setResponseMessage('Prepared configuration loaded; no server request')
    SampleResult.setSuccessful(true)
} catch (Exception ignored) {
    if (props.getProperty('athena.shared_account') == 'true') log.error('Shared configuration setup failed: ' + ignored.getClass().getName() + ' at ' + ignored.getStackTrace().take(4).join(' <- '))
    SampleResult.setResponseCode('CONFIG_ERROR')
    SampleResult.setResponseMessage('Configuration/session setup failed. Check the prepared run and account count.')
    SampleResult.setSuccessful(false)
    SampleResult.setStopThread(true)
}
