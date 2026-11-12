import { readFile, writeFile, mkdir, access } from 'node:fs/promises';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createHash } from 'node:crypto';
import { operations, workspaces, baseUrl, payloadExamples } from './scenarios.mjs';

const root = dirname(fileURLToPath(import.meta.url));
const xml = value => String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
const prop = (name,value,type='string') => `<${type}Prop name="${name}">${xml(name==='ThreadGroup.scheduler'?false:value)}</${type}Prop>`;
function scriptElement(kind, name, code, parameters='') {
    return `<${kind} guiclass="TestBeanGUI" testclass="${kind}" testname="${xml(name)}" enabled="true">${prop('scriptLanguage','groovy')}${prop('parameters',parameters)}${prop('filename','')}${prop('cacheKey','true')}${prop('script',code)}</${kind}><hashTree/>`;
}
export function normalizeBaseUrl(value) {
    const url = new URL(value);
    if (!['http:','https:'].includes(url.protocol) || url.username || url.password) throw new Error('Use an HTTP(S) website address without credentials.');
    url.search=''; url.hash='';
    return url.href.replace(/\/$/,'');
}
export function exampleConfig(workspace='research_head') {
    if (!workspaces[workspace]) throw new Error('Unknown workspace.');
    return { baseUrl, workspace, users: [{sessionFile:`private/${workspace}-01.json`, records:{}, payloads:{}}],
        threads:1, rampUpSeconds:1, loops:1, warmupLoops:0, durationSeconds:0, pauseMs:1000,
        operations:operations.filter(op=>op.roles.includes(workspace) && !op.write && !op.external && !op.records && !op.optional).map(op=>op.id),
        isolatedTestEnvironment:false };
}
export async function prepareConfig(input, configPath, options={}) {
    const c=structuredClone(input);
    c.baseUrl=normalizeBaseUrl(c.baseUrl);
    if (!workspaces[c.workspace]) throw new Error('Choose a supported workspace from the list command.');
    for (const [key,min] of [['threads',1],['rampUpSeconds',1],['loops',1],['warmupLoops',0],['durationSeconds',0],['pauseMs',0]]) {
        if (!Number.isInteger(c[key]) || c[key]<min) throw new Error(`${key} must be an integer >= ${min}.`);
    }
    if (!Array.isArray(c.users) || c.users.length!==c.threads) throw new Error('Provide exactly one independent saved session in users for each thread.');
    if (!Array.isArray(c.operations) || !c.operations.length || new Set(c.operations).size!==c.operations.length) throw new Error('Select a nonempty list of unique operation IDs.');
    const selected = c.operations.map(id=> {
        const op=operations.find(x=>x.id===id);
        if (!op || !op.roles.includes(c.workspace)) throw new Error(`Operation not available for ${c.workspace}: ${id}`);
        return structuredClone(op);
    });
    for (const op of selected) if(op.depends && (!c.operations.includes(op.depends) || c.operations.indexOf(op.depends)>c.operations.indexOf(op.id))) throw new Error(`${op.id} requires ${op.depends} earlier in operations.`);
    if (selected.some(x=>x.write)) {
        if(!options.allowWrites || c.isolatedTestEnvironment!==true) throw new Error('Submission/write scenarios require --allow-writes and isolatedTestEnvironment:true with disposable records.');
        if(c.loops!==1 || c.warmupLoops!==0 || c.durationSeconds!==0) throw new Error('Write scenarios require loops:1, warmupLoops:0, durationSeconds:0; use fresh eligible fixtures for each repetition.');
    }
    if(selected.some(x=>x.external) && !options.allowExternal) throw new Error('Live external-service scenarios require --allow-external and actual payloads.');
    c.allowWrites=Boolean(options.allowWrites); c.allowExternal=Boolean(options.allowExternal);
    c.runId=new Date().toISOString().replace(/[:.]/g,'-');
    [c.dashboard,c.workspaceMarker]=workspaces[c.workspace];
    c.operations=selected;
    const sessions=options.sessions ?? new Set();
    const fixtures=options.fixtures ?? new Set();
    for(const [i,user] of c.users.entries()) {
        user.records ??={}; user.payloads ??={};
        user.sessionFile=resolve(dirname(configPath),user.sessionFile);
        const state=JSON.parse(await readFile(user.sessionFile,'utf8'));
        const site=new URL(c.baseUrl);
        const cookies=(state.cookies??[]).filter(k=>site.hostname===k.domain.replace(/^\./,'') || site.hostname.endsWith('.'+k.domain.replace(/^\./,'')));
        const active=cookies.filter(k=>k.name!=='XSRF-TOKEN' && /session/i.test(k.name) && (!k.expires || k.expires<0 || k.expires>Date.now()/1000));
        if(!active.length) throw new Error(`User ${i+1}: no unexpired ATHENA session cookie. Capture the session again.`);
        const fingerprint=createHash('sha256').update(active.map(k=>k.name+'='+k.value).sort().join(';')).digest('hex');
        if(sessions.has(fingerprint)) throw new Error('Duplicate session cookie: each virtual user needs its own session, not copied browser state.');
        sessions.add(fingerprint);
        for(const records of user.recordSets ?? [user.records]) for(const op of selected) {
            for(const key of op.records??[]) {
                const value=String(records[key]??'');
                const valid=key==='manual_version' ? /^[a-f0-9]{64}$/.test(value) : /^[1-9][0-9]*$/.test(value);
                if(!valid) throw new Error(`User ${i+1}: ${op.id} requires a real test ${key}.`);
            }
            if(op.payload && (!Object.hasOwn(user.payloads,op.id) || user.payloads[op.id]===null || Array.isArray(user.payloads[op.id]) || typeof user.payloads[op.id]!=='object')) throw new Error(`User ${i+1}: supply an object in payloads["${op.id}"]. Use {} for a prepared submission with no other form fields.`);
            if(op.write && op.records?.length) {
                const key=op.id+':'+op.records.map(k=>records[k]).join(':');
                if(fixtures.has(key)) throw new Error(`Shared write fixture for ${op.id}; assign different disposable records per user.`);
                fixtures.add(key);
            }
        }
    }
    return c;
}
export async function buildPlan(selectedOperations=operations) {
    const [boot,request,assertion]=await Promise.all(['bootstrap.groovy','request.groovy','assert-response.groovy'].map(x=>readFile(join(root,x),'utf8')));
    function http(op) {
        return `<HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="${xml(op.label)}" enabled="true"><elementProp name="HTTPsampler.Arguments" elementType="Arguments"><collectionProp name="Arguments.arguments"/></elementProp>${prop('HTTPSampler.domain','127.0.0.1')}${prop('HTTPSampler.port','1')}${prop('HTTPSampler.protocol','http')}${prop('HTTPSampler.path','/configuration-required')}${prop('HTTPSampler.method','GET')}${prop('HTTPSampler.follow_redirects',false,'bool')}${prop('HTTPSampler.auto_redirects',false,'bool')}${prop('HTTPSampler.use_keepalive',true,'bool')}${prop('HTTPSampler.connect_timeout','10000')}${prop('HTTPSampler.response_timeout','120000')}</HTTPSamplerProxy><hashTree><HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="Request headers" enabled="true"><collectionProp name="HeaderManager.headers"><elementProp name="User-Agent" elementType="Header">${prop('Header.name','User-Agent')}${prop('Header.value','ATHENA-Capstone-JMeter')}</elementProp></collectionProp></HeaderManager><hashTree/>${scriptElement('JSR223PreProcessor','Prepare request and session',request,op.id)}${scriptElement('JSR223Assertion','Verify status, session and content',assertion,op.id)}</hashTree>`;
    }
    const groups=selectedOperations.map(op=>`<IfController guiclass="IfControllerPanel" testclass="IfController" testname="${xml(op.module+' / '+op.id)}" enabled="true">${prop('IfController.condition','${__groovy(vars.get("enabled_'+op.id+'") == "true")}')}${prop('IfController.evaluateAll',false,'bool')}${prop('IfController.useExpression',true,'bool')}</IfController><hashTree>${http({id:op.id,label:`PERF | ${op.id} | ${op.name}`})}</hashTree>`).join('\n');
    return `<?xml version="1.0" encoding="UTF-8"?>\n<jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3"><hashTree><TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="ATHENA deployed module benchmarks" enabled="true">${prop('TestPlan.comments','Prepared HTTP/backend tests. Google sign-in must be captured first. No browser rendering, external Turnitin load, or unconfigured record submissions are implied. Load run.properties with -q before execution.')}${prop('TestPlan.functional_mode',false,'bool')}<elementProp name="TestPlan.user_defined_variables" elementType="Arguments" guiclass="ArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true"><collectionProp name="Arguments.arguments"/></elementProp></TestPlan><hashTree><ThreadGroup guiclass="ThreadGroupGui" testclass="ThreadGroup" testname="Independent ATHENA sessions" enabled="true">${prop('ThreadGroup.on_sample_error','stopthread')}<elementProp name="ThreadGroup.main_controller" elementType="LoopController" guiclass="LoopControlPanel" testclass="LoopController" testname="Iterations" enabled="true">${prop('LoopController.continue_forever',false,'bool')}${prop('LoopController.loops','${__P(athena.loops,1)}')}</elementProp>${prop('ThreadGroup.num_threads','${__P(athena.users,1)}')}${prop('ThreadGroup.ramp_time','${__P(athena.ramp_up,1)}')}${prop('ThreadGroup.scheduler','${__P(athena.scheduler,false)}','bool')}${prop('ThreadGroup.duration','${__P(athena.duration,0)}')}${prop('ThreadGroup.delay','0')}${prop('ThreadGroup.same_user_on_next_iteration',true,'bool')}</ThreadGroup><hashTree><CookieManager guiclass="CookiePanel" testclass="CookieManager" testname="Per-user session cookies" enabled="true"><collectionProp name="CookieManager.cookies"/>${prop('CookieManager.clearEachIteration',false,'bool')}</CookieManager><hashTree/><ConstantTimer guiclass="ConstantTimerGui" testclass="ConstantTimer" testname="Pause before each request" enabled="true">${prop('ConstantTimer.delay','${__P(athena.pause_ms,1000)}')}</ConstantTimer><hashTree/><OnceOnlyController guiclass="OnceOnlyControllerGui" testclass="OnceOnlyController" testname="Authenticated setup once per user" enabled="true"/><hashTree>${scriptElement('JSR223Sampler','SETUP | Load local run configuration',boot)}${http({id:'session',label:'SETUP | Verify authenticated workspace'})}</hashTree>${groups}</hashTree></hashTree></hashTree></jmeterTestPlan>\n`;
}
export async function writeRun(config, coverage, parent='test-results/jmeter') {
    const dir = resolve(parent, config.runId);
    await mkdir(resolve(parent), { recursive: true });
    await mkdir(dir);
    let plan = (await buildPlan(config.operations)).replace('<boolProp name="ThreadGroup.scheduler">false</boolProp>', `<boolProp name="ThreadGroup.scheduler">${config.durationSeconds > 0}</boolProp>`);
    if (config.warmupSeconds > 0) plan = plan.replace('name="ThreadGroup.on_sample_error">stopthread', 'name="ThreadGroup.on_sample_error">continue');
    if (config.loadModel === 'shared-account') plan = plan.replace('testname="Independent ATHENA sessions"', 'testname="Virtual users sharing 3 accounts"');
    await writeFile(join(dir, 'athena.jmx'), plan);
    await writeFile(join(dir, 'runtime.local.json'), JSON.stringify(config, null, 2) + '\n');
    const properties = {
        'athena.config': join(dir, 'runtime.local.json').replaceAll('\\', '/'),
        'athena.shared_account': config.loadModel === 'shared-account',
        'athena.users': config.threads, 'athena.ramp_up': config.rampUpSeconds,
        'athena.loops': config.durationSeconds > 0 ? -1 : config.loops + config.warmupLoops,
        'athena.duration': config.durationSeconds, 'athena.pause_ms': config.pauseMs,
        'sample_variables': 'workspace,actor_index,project_key',
        'jmeter.save.saveservice.output_format': 'csv', 'jmeter.save.saveservice.print_field_names': true,
        'jmeter.save.saveservice.response_data': false, 'jmeter.save.saveservice.response_data.on_error': false,
        'jmeter.save.saveservice.samplerData': false, 'jmeter.save.saveservice.requestHeaders': false,
        'jmeter.save.saveservice.responseHeaders': false, 'jmeter.save.saveservice.timestamp_format': 'ms',
        'jmeter.reportgenerator.sample_filter': '^PERF \\| .*',
    };
    // Java properties need two backslashes to preserve the report filter's literal pipe.
    await writeFile(join(dir, 'run.properties'), Object.entries(properties).map(([k, v]) => `${k}=${String(v).replaceAll('\\', '\\\\')}`).join('\n') + '\n');
    await writeFile(join(dir, 'coverage.json'), JSON.stringify(coverage, null, 2) + '\n');
    return dir;
}

export const help = `ATHENA JMeter kit (run commands from src)

node scripts/jmeter/athena.mjs list [workspace]
node scripts/jmeter/athena.mjs init [workspace]
node scripts/jmeter/capture-session.mjs --workspace research_head --out scripts/jmeter/private/research_head-01.json
node scripts/jmeter/athena.mjs prepare scripts/jmeter/run.local.json [--allow-writes] [--allow-external]
node scripts/jmeter/summarize.mjs test-results/jmeter/RUN/results.jtl

Workspaces: ${Object.keys(workspaces).join(', ')}
init creates an ignored run.local.json with a one-user baseline, and prints payload examples.
Capture a fresh Google-authenticated session for each virtual user. Cookies stay in ignored private files.
Edit operations and per-user records/payloads using list. Missing fixtures stop preparation; nothing is silently counted as tested.
prepare makes a JMX, run.properties, coverage.json and local runtime configuration. It sends NO HTTP requests.
Use Run-JMeter.ps1 with the printed run directory to execute using your installed JMeter.
For read load: set threads and one session per thread; use warmupLoops then loops, or durationSeconds for sustained tests.
For submissions: use an isolated testing copy, explicit --allow-writes, one iteration, no warmup/duration, fresh eligible test records.
External AI/discovery calls require --allow-external. Existing application rate limits still apply; 429 is a failure, never bypassed.
HTML and Livewire refresh are separate samples. This does not measure complete browser rendering or automatically exercise every form.
File uploads, narrative/terminal submission and final approval/NTP creation are NOT automated by this kit. Document downloads, complete proposal submission and prepared monitoring submission are available.
Turnitin is outside the load target. No request follows redirects to Google or other third-party hosts.
Setup and warmup samples are excluded from measured summaries. Keep coverage.json and use the same scenario, records and timing between load levels.
Write runs leave test records and may trigger configured notifications. Reset test fixtures separately before another run; no cleanup deletes are performed.
`;
export async function main(args=process.argv.slice(2)) {
    const [command,arg]=args;
    if(command==='list') {
        const rows=operations.filter(x=>!arg || x.roles.includes(arg));
        for(const op of rows) console.log(`${op.id.padEnd(28)} ${op.method.padEnd(4)} ${op.path} | ${op.module} | ${op.write?'WRITE':op.external?'EXTERNAL':'READ'}${op.component?' (Livewire refresh)':''}${op.records?' | records: '+op.records.join(','):''}${op.depends?' | after: '+op.depends:''}${op.payload?' | payload required':''}${op.note?' | '+op.note:''}`);
        return;
    }
    if(command==='init') {
        const p=join(root,'run.local.json');
        try{await access(p);throw new Error('run.local.json already exists; edit it or choose another file manually.');}catch(e){if(e.code!=='ENOENT')throw e;}
        await mkdir(join(root,'private'),{recursive:true});
        await writeFile(p,JSON.stringify(exampleConfig(arg),null,2)+'\n');
        console.log(`Created ${p}\n\nPayload examples (copy only the operations you select):\n${JSON.stringify(payloadExamples,null,2)}\n\n${help}`);return;
    }
    if(command==='build') {await writeFile(join(root,'athena.jmx'),await buildPlan());console.log('Built scripts/jmeter/athena.jmx without making server requests.');return;}
    if(command==='prepare') {
        const configPath=resolve(arg??join(root,'run.local.json'));
        const c=await prepareConfig(JSON.parse(await readFile(configPath,'utf8')),configPath,{allowWrites:args.includes('--allow-writes'),allowExternal:args.includes('--allow-external')});
        const dir=resolve('test-results/jmeter',c.runId);await mkdir(dir,{recursive:true});
        const plan=(await buildPlan()).replace('<boolProp name="ThreadGroup.scheduler">false</boolProp>',`<boolProp name="ThreadGroup.scheduler">${c.durationSeconds>0}</boolProp>`);
        await writeFile(join(dir,'athena.jmx'),plan);
        await writeFile(join(dir,'runtime.local.json'),JSON.stringify(c,null,2)+'\n');
        const properties={ 'athena.config':join(dir,'runtime.local.json').replaceAll('\\','/'),'athena.users':c.threads,'athena.ramp_up':c.rampUpSeconds,'athena.loops':c.durationSeconds>0?-1:c.loops+c.warmupLoops,'athena.scheduler':c.durationSeconds>0,'athena.duration':c.durationSeconds,'athena.pause_ms':c.pauseMs,
            'jmeter.save.saveservice.output_format':'csv','jmeter.save.saveservice.print_field_names':true,'jmeter.save.saveservice.response_data':false,'jmeter.save.saveservice.response_data.on_error':false,'jmeter.save.saveservice.samplerData':false,'jmeter.save.saveservice.requestHeaders':false,'jmeter.save.saveservice.responseHeaders':false,'jmeter.save.saveservice.timestamp_format':'ms'};
        await writeFile(join(dir,'run.properties'),Object.entries(properties).map(([k,v])=>`${k}=${v}`).join('\n')+'\n');
        const coverage={baseUrl:c.baseUrl,workspace:c.workspace,threads:c.threads,loops:c.loops,warmupLoops:c.warmupLoops,durationSeconds:c.durationSeconds,pauseMs:c.pauseMs,runId:c.runId,measurement:'HTTP response duration; separate Livewire component refresh samples; not browser rendering',operations:operations.map(op=>({id:op.id,module:op.module,name:op.name,selected:c.operations.some(x=>x.id===op.id),reason:!op.roles.includes(c.workspace)?'Different workspace':c.operations.some(x=>x.id===op.id)?'Configured, not yet executed':'Not selected; no coverage claim'})),excluded:['Google sign-in performance','Turnitin processing','Browser paint and JavaScript execution','File upload transport','Final approval and Notice to Proceed creation']};
        await writeFile(join(dir,'coverage.json'),JSON.stringify(coverage,null,2)+'\n');
        console.log(`Prepared ${c.operations.length} operations for ${c.threads} user(s). No HTTP requests sent.\nRun directory: ${dir}\nExecute when ready:\npowershell -ExecutionPolicy Bypass -File scripts/jmeter/Run-JMeter.ps1 -RunDirectory "${dir}" -JMeterPath "C:/path/to/apache-jmeter/bin/jmeter.bat"`);return;
    }
    console.log(help);
}
if(process.argv[1] && import.meta.url===pathToFileURL(resolve(process.argv[1])).href) main().catch(e=>{console.error(e.message);process.exitCode=1;});
