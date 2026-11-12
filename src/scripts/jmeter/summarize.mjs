import { readFile, writeFile } from 'node:fs/promises';
import { resolve, dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';

export function parseCsv(text) {
    const rows=[];let row=[],cell='',quoted=false;
    for(let i=0;i<text.length;i++) {
        const c=text[i];
        if(c==='"') {if(quoted && text[i+1]==='"'){cell+='"';i++;}else quoted=!quoted;}
        else if(c===',' && !quoted){row.push(cell);cell='';}
        else if(c==='\n' && !quoted){row.push(cell.replace(/\r$/,''));if(row.some(x=>x!==''))rows.push(row);row=[];cell='';}
        else cell+=c;
    }
    if(quoted)throw new Error('Unclosed CSV quote.');
    if(cell || row.length){row.push(cell.replace(/\r$/,''));rows.push(row);}
    return rows;
}
export function summarize(text, coverage={}) {
    const [header,...rows]=parseCsv(text.replace(/^\uFEFF/,''));
    for(const name of ['timeStamp','elapsed','label','success'])if(!header?.includes(name))throw new Error(`Missing JTL column: ${name}`);
    const data=rows.map(row=>Object.fromEntries(header.map((name,i)=>[name,row[i]??''])));
    const measured=data.filter(x=>x.label.startsWith('PERF | '));
    const setupFailures=data.filter(x=>x.label.startsWith('SETUP | ') && x.success!=='true').length;
    const warmupFailures=data.filter(x=>x.label.startsWith('WARMUP | ') && x.success!=='true').length;
    if(!measured.length)return {measurementStatus:'No measured requests',setupFailures,warmupFailures,operations:[]};
    for(const r of measured)if(!Number.isFinite(Number(r.elapsed)) || Number(r.elapsed)<0 || !Number.isFinite(Number(r.timeStamp)) || !['true','false'].includes(r.success))throw new Error('Invalid elapsed, timestamp or success value in measured data.');
    const start=measured.reduce((n,x)=>Math.min(n,Number(x.timeStamp)),Infinity);
    const end=measured.reduce((n,x)=>Math.max(n,Number(x.timeStamp)+Number(x.elapsed)),-Infinity);
    const seconds=(end-start)/1000;
    const groups=new Map();for(const row of measured){if(!groups.has(row.label))groups.set(row.label,[]);groups.get(row.label).push(row);}
    const times = measured.map(row => Number(row.elapsed)).sort((a, b) => a - b);
    const measuredFailures = measured.filter(row => row.success !== 'true').length;
    const valid = measured.filter(row => row.success === 'true');
    const actualJourneys = Object.fromEntries(Object.entries(coverage.journeys ?? {}).map(([role, ids]) => {
        const samples = valid.filter(row => row.workspace === role);
        return [role, { expectedUsers: coverage.roleCounts[role], measuredUsers: new Set(samples.map(row => row.actor_index)).size,
            minimumTopics: coverage.minimumTopics?.[role] ?? (role === 'vcrdes' ? 0 : 2),
            distinctTopics: new Set(samples.map(row => row.project_key).filter(key => /^[1-9]\d*$/.test(key))).size,
            missingOperations: ids.filter(id => !samples.some(row => row.label.split(' | ')[1] === id)) }];
    }));
    return {measurementStatus:setupFailures||warmupFailures?'Incomplete run: setup or warmup failed':'Measured requests recorded',
        ...(coverage.loadModel === 'shared-account' ? { loadModel: coverage.loadModel, accountCount: coverage.accountCount,
            sharedSessionCount: coverage.sharedSessionCount, virtualUsers: coverage.virtualUsers, limitations: coverage.limitations } : {}),
        setupFailures,warmupFailures,measurementStartMs:start,measurementEndMs:end,measurementSeconds:seconds,
        total: { samples: measured.length, failed: measuredFailures, meanMs: times.reduce((sum, time) => sum + time, 0) / times.length,
            p95Ms: times[Math.ceil(times.length * .95) - 1], maxMs: times.at(-1), requestsPerSecond: measured.length / (coverage.measurementSeconds ?? (seconds || 1)),
            peakActiveUsers: Math.max(...measured.map(row => Number(row.grpThreads) || 0)),
            distinctTopics: new Set(valid.map(row => row.project_key).filter(key => /^[1-9]\d*$/.test(key))).size },
        actualJourneys,
        definitions:{throughput:'Completed measured requests divided by the common measured interval; includes pauses/ramp-up between measured requests.',p95:'Nearest-rank percentile of recorded elapsed milliseconds.',failureRate:'Failed measured requests / completed measured requests. Inspect JMeter logs separately for unrecorded/preprocessor failures.'},
        operations:[...groups].map(([label,samples])=>{
            const times=samples.map(x=>Number(x.elapsed)).sort((a,b)=>a-b);const failed=samples.filter(x=>x.success!=='true').length;
            return {label,samples:samples.length,successful:samples.length-failed,failed,meanMs:times.reduce((a,b)=>a+b,0)/times.length,p95Ms:times[Math.ceil(times.length*.95)-1],minMs:times[0],maxMs:times.at(-1),requestsPerSecond:seconds>0?samples.length/seconds:null,successfulRequestsPerSecond:seconds>0?(samples.length-failed)/seconds:null,failureRatePercent:failed/samples.length*100};
        })};
}
async function main() {
    if(!process.argv[2])throw new Error('Usage: node scripts/jmeter/summarize.mjs path/to/results.jtl');
    const file=resolve(process.argv[2]);
    const coverage=await readFile(join(dirname(file),'coverage.json'),'utf8').then(JSON.parse).catch(error=>{
        if(error.code==='ENOENT')return {};
        throw error;
    });
    const report=summarize(await readFile(file,'utf8'),coverage);
    const keys=['label','samples','successful','failed','meanMs','p95Ms','minMs','maxMs','requestsPerSecond','successfulRequestsPerSecond','failureRatePercent'];
    const escape=x=>'"'+String(x??'').replaceAll('"','""')+'"';
    await writeFile(join(dirname(file),'summary.json'),JSON.stringify(report,null,2)+'\n');
    await writeFile(join(dirname(file),'summary.csv'),[keys.join(','),...report.operations.map(row=>keys.map(k=>escape(row[k])).join(','))].join('\n')+'\n');
    console.log(`${report.measurementStatus}. ${report.operations.length} operation labels. Saved summary.csv and summary.json beside the JTL.`);
    const incomplete = Object.entries(report.actualJourneys ?? {}).filter(([role, journey]) =>
        journey.expectedUsers !== journey.measuredUsers || journey.missingOperations.length || journey.distinctTopics < journey.minimumTopics);
    if (incomplete.length) console.error(`Incomplete role or project coverage: ${incomplete.map(([role]) => role).join(', ')}.`);
    if(!report.operations.length || report.setupFailures || report.warmupFailures || report.operations.some(x=>x.failed) || incomplete.length)process.exitCode=1;
}
if(process.argv[1] && import.meta.url===pathToFileURL(resolve(process.argv[1])).href)main().catch(e=>{console.error(e.message);process.exitCode=1;});
