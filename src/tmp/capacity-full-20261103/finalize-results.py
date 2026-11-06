import hashlib
import json
import shutil
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

run = Path('/var/tmp/athena-capacity-20261103')
output = Path('/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103')
names = ['2g-probe-clean', '2g-matrix', '2g-browse-during-rms', '2g-additional', '2g-additional-fixed',
         '2g-browse-1000', '2g-highres', '4g-highres', '4g-browse-1000', '4g-burst-1000', '4g-soak-20m', '4g-terminal-240']
reports = [json.loads((run / 'results' / name / 'report.json').read_text()) for name in names]
assert all(report.get('finished_at') for report in reports), 'A required capacity run has not finished.'
soak = next(report for report in reports if report['name'] == '4g-soak-20m')
soak_phase = next(phase for phase in soak['phases'] if phase['phase'] == 'soak')
assert soak_phase['seconds'] >= 1200, 'The sustained workload did not run for twenty minutes.'
retry = next(report for report in reports if report['name'] == '4g-terminal-240')
retry_phase = next(phase for phase in retry['phases'] if phase['phase'] == 'combined')
retry_heavy = retry_phase['result']['heavy'][0]
assert retry_heavy['status'] == 200, 'The longer-timeout control case did not pass.'


def result_summary(result):
    detail = result.get('result', {})
    return {**{key: result[key] for key in ['case', 'status', 'seconds', 'count', 'errors', 'p50_seconds', 'p95_seconds', 'maximum_seconds', 'statuses'] if key in result},
            **{key: detail[key] for key in ['case', 'pages', 'input_counts', 'php_peak_mib', 'conversion_seconds', 'source_generation_seconds', 'fixture_and_validation_seconds', 'total_image_pixels', 'converter_timeout_seconds'] if key in detail},
            'heavy': [result_summary(row) for row in result.get('heavy', [])]}


summary = {
    'completed_at_utc': datetime.now(timezone.utc).isoformat(),
    'scope': 'Local synthetic Linux capacity assessment; no production application files changed and no cloud resources created.',
    'assessment': {'recommended_start': '4GiB/2vCPU for the current heavy workloads;2GiB only for controlled lighter use or after image optimization.',
                   'soak_seconds_including_final_job_drain': soak_phase['seconds'],
                   'soak_browsing_requests': soak_phase['result']['count'], 'soak_browsing_errors': soak_phase['result']['errors'],
                   'soak_heavy_jobs': len(soak_phase['result']['heavy']),
                   'soak_heavy_failures': sum(row['status'] != 200 for row in soak_phase['result']['heavy']),
                   'soak_oom_kills': soak['memory_events_end']['oom_kill'],
                   'pdf_timeout_retry_seconds': retry_heavy['seconds'], 'pdf_timeout_retry_passed': retry_heavy['status'] == 200,
                   'remaining_application_issues': ['LargeRMS repeatedwhole-documentXMLlookups', 'LibreOfficeprocess-treecleanup aftertimeout'],
                   'validated_pdf_timeout_adjustment_seconds': 240},
    'runtime': {'system': 'Ubuntu26.04 chroot in WSL2, cgroupv2 CPU/RAM limits, private network namespace',
                'php': 'Ubuntu8.5.4-0ubuntu1.5 security package', 'framework': 'Laravel13.12.0',
                'mysql': '8.4.11', 'libreoffice': '26.2.6.3', 'node': '22.22.1', 'poppler': '26.01.0'},
    'method': {'reserved_os_mib': 256, 'swap_mib': 0, 'sampling_interval_seconds': .25,
               'server_processes': ['Nginx', 'PHP-FPM', 'MySQL128MiBbuffer', 'PDFqueueworker', 'defaultqueueworker', 'Reverb20WebSockets', 'PDFrecovery/persistentLibreOffice', 'scheduler'],
               'clients_and_sampler': 'Outside the limited server cgroup',
               'peak_pss': 'Sum of proportional resident memory; shared pages counted proportionally',
               'cgroup_memory': 'Includes charged file cache and kernel memory; cache ownership can differ across service restarts'},
    'findings': [
        {'id': '2g-photo-oom', 'outcome': 'Twelve distinct20MPJPEGs totaling61411049bytes caused one LinuxOOMkill and HTTP500 on the2GiB simulation; the identical input passed on4GiB.', 'evidence': ['2g-highres', '4g-highres']},
        {'id': 'large-rms-cpu', 'outcome': '1500rows on each of3sheets exceeded540seconds of PHPexecution time in RmsQuarterlyReportDocument::cell atDOMXPathquery,line191; noOOM in that run.', 'evidence': ['2g-matrix', 'linux-logs/capacity-php.log']},
        {'id': 'marker-checks', 'outcome': 'Initial terminal/quarterly marker failures came from extracted-text reading order. Complete markers were verified and both workloads rerun successfully.', 'evidence': ['2g-additional', 'evidence/marker-recheck.json']},
        {'id': 'fixture-corrections', 'outcome': 'Initial additional archive/retrieval failures were synthetic fixture issues: a required summary field and a nondiscriminating repeated query. Corrected cases passed.', 'evidence': ['2g-additional-fixed']},
        {'id': 'huge-pdf-timeout', 'outcome': 'One415page terminalPDFhit the existing120second conversion timeout during the4GiB soak, withoutOOM. The separately labelled240second retry tests a deployment timeout adjustment; no application source change.', 'evidence': ['4g-soak-20m', '4g-terminal-240', 'linux-logs/laravel.log']},
        {'id': 'worker-recovery-process', 'outcome': 'After the timeout, the oldLibreOfficebinary remained withPPID1 using837748KiBRSS, while recovery started anotherworker on a differentUNOpipeline. The oldpipeline matches the timeoutlog. LibreOfficePdfWorker::stop signals the launcher atline134; Linuxprocess-treecleanup needs review.', 'evidence': ['worker-process-snapshot.txt', 'linux-logs/laravel.log', 'app/Services/LibreOfficePdfWorker.php:134']},
    ],
    'limitations': [
        'LocalWSLCPU and disk performance are not a DigitalOcean benchmark; cloud latency and sharedCPUvariation were not measured.',
        'PHP8.5.4 Ubuntu backport differs from the project Composerpatch requirement; deploy with a compatible patchedPHPversion and smoke-test there.',
        'Only remoteAIHTTPwas mocked; localGD,Poppler,LibreOffice,Node and MySQLwork were real. PaidAIlatency/usage was not tested.',
        'Finite synthetic workloads do not prove capacity for unbounded report rows, tables, image counts, or future traffic.',
        'Production built assets were served; frontend builds,Composerinstallation and development servers were not part of runtime memory measurements.',
    ],
    'runs': [],
}
for report in reports:
    summary['runs'].append({key: report.get(key) for key in ['name', 'limits', 'server_config', 'started_at', 'finished_at', 'memory_events_end', 'cgroup_peak_mib', 'fatal', 'reverb_heartbeat_errors']}
                          | {'phases': [{'phase': phase['phase'], 'seconds': phase['seconds'],
                                         'resources': {key: value for key, value in phase['resources'].items() if key != 'processes_at_peak'},
                                         'result': result_summary(phase['result'])} for phase in report['phases']]})
evidence = output / 'evidence'
evidence.mkdir(exist_ok=True)
shutil.copy2(output / 'worker-process-snapshot.txt', evidence / 'worker-process-snapshot.txt')
artifacts = run / 'rootfs/app/storage/app/private/capacity-output'
for path in artifacts.glob('*-result.json'):
    shutil.copy2(path, evidence / path.name)
for name in ['marker-recheck.json']:
    shutil.copy2(artifacts / name, evidence / name)
for source, destination in [(run / 'rootfs/app/storage/app/private/capacity-fixtures/manifest.json', 'fixture-manifest.json'),
                            (run / 'rootfs/app/storage/app/private/capacity-fixtures/other-manifest.json', 'journal-fixture-manifest.json'),
                            (run / 'rootfs/app/storage/app/private/capacity-photos/manifest.json', 'photo-fixture-manifest.json')]:
    shutil.copy2(source, evidence / destination)
for name in ['mysql.cnf', 'php-fpm.conf', 'supervisord.conf', 'nginx.conf']:
    shutil.copy2(run / 'rootfs/app/benchmark' / name, evidence / ('final-server-' + name))
hashes = {}
for name in ['source.tar', 'vendor.tar']:
    digest = hashlib.sha256()
    with (output / name).open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(block)
    hashes[name] = digest.hexdigest()
summary['snapshot_sha256'] = hashes
(output / 'capacity-report.json').write_text(json.dumps(summary, indent=2))
with ZipFile(output / 'measurement-evidence.zip', 'w', ZIP_DEFLATED, compresslevel=6) as archive:
    for name in names:
        for path in (run / 'results' / name).glob('*'):
            if path.is_file():
                archive.write(path, 'results/' + name + '/' + path.name)
    for path in evidence.iterdir():
        archive.write(path, 'evidence/' + path.name)
    for path in (run / 'rootfs/app/storage/logs').iterdir():
        if path.is_file():
            archive.write(path, 'linux-logs/' + path.name)
    archive.write(output / 'capacity-report.json', 'capacity-report.json')
with ZipFile(output / 'measurement-evidence.zip') as archive:
    assert archive.testzip() is None
print(json.dumps({'report': str(output / 'capacity-report.json'), 'evidence_zip_bytes': (output / 'measurement-evidence.zip').stat().st_size,
                  'assessment': summary['assessment']}))
