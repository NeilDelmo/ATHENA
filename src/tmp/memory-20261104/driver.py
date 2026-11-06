"""Disposable Linux capacity test client; deliberately outside the server cgroup."""

import argparse
import concurrent.futures
import http.cookiejar
import json
import math
import os
import socket
import statistics
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path('/var/tmp/athena-memory-20261104')
TOKEN = 'capacity-local-synthetic-20261103-token-7fba39881'
BASE = 'http://127.0.0.1:8000'
CONTEXT = json.loads((ROOT / 'rootfs/app/storage/app/private/capacity-context.json').read_text())


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, role='head'):
        self.role = role
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        user = CONTEXT['users'][role]
        result = self.get(f"/__capacity/login/{user['id']}?workspace={user['workspace']}&token={TOKEN}")
        assert result['status'] == 200, {key: value for key, value in result.items() if key != 'body'}
        assert json.loads(result['body'])['user_id'] == user['id']

    def get(self, path, timeout=600):
        start = time.monotonic()
        try:
            with self.opener.open(BASE + path, timeout=timeout) as response:
                body = response.read()
                status = response.status
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read()
        except Exception as error:
            status, body = 0, str(error).encode()
        return {'status': status, 'seconds': time.monotonic() - start, 'bytes': len(body), 'body': body}

    def task(self, case):
        result = self.get(f'/__capacity/task/{case}?token={TOKEN}')
        if result['status'] == 200:
            try:
                result['result'] = json.loads(result['body'])
            except Exception as error:
                result['parse_error'] = str(error)
        body = result.pop('body')
        result['body_excerpt'] = body[:1000].decode(errors='replace') if result['status'] != 200 else None
        return result


class Monitor:
    def __init__(self, cgroup, output):
        self.cgroup = Path('/sys/fs/cgroup') / cgroup.lstrip('/')
        self.output = output
        self.phase = 'starting'
        self.rows = []
        self.running = True
        self.thread = threading.Thread(target=self.run, daemon=True)
        self.thread.start()

    def read_map(self, name):
        return {parts[0]: int(parts[1]) for line in (self.cgroup / name).read_text().splitlines() if len(parts := line.split()) == 2}

    def snapshot(self):
        memory = self.read_map('memory.stat')
        row = {'time': time.time(), 'phase': self.phase,
               'memory_bytes': int((self.cgroup / 'memory.current').read_text()),
               'peak_bytes': int((self.cgroup / 'memory.peak').read_text()),
               'anon_bytes': memory['anon'], 'file_bytes': memory['file'],
               'kernel_bytes': memory.get('kernel', 0),
               'events': self.read_map('memory.events'), 'cpu': self.read_map('cpu.stat')}
        processes = []
        for pid in (self.cgroup / 'cgroup.procs').read_text().split():
            try:
                process = Path('/proc') / pid
                status = dict(line.split(':', 1) for line in (process / 'status').read_text().splitlines() if ':' in line)
                rollup = dict(line.split(':', 1) for line in (process / 'smaps_rollup').read_text().splitlines() if ':' in line)
                cmd = (process / 'cmdline').read_bytes().replace(b'\0', b' ').decode(errors='replace')[:220]
                processes.append({'pid': int(pid), 'name': status['Name'].strip(),
                                  'rss_kib': int(status.get('VmRSS', '0 kB').split()[0]),
                                  'pss_kib': int(rollup.get('Pss', '0 kB').split()[0]), 'command': cmd})
            except (OSError, ValueError):
                pass
        row['processes'] = processes
        row['resident_pss_mib'] = sum(process['pss_kib'] for process in processes) / 1024
        return row

    def run(self):
        with (self.output / 'samples.jsonl').open('w', buffering=65536) as stream:
            while self.running:
                try:
                    row = self.snapshot()
                    self.rows.append(row)
                    stream.write(json.dumps(row, separators=(',', ':')) + '\n')
                except OSError as error:
                    stream.write(json.dumps({'error': str(error), 'time': time.time()}) + '\n')
                time.sleep(.25)

    def summarize(self, phase):
        rows = [row for row in self.rows if row['phase'] == phase]
        if not rows:
            return {}
        peak = max(rows, key=lambda row: row['memory_bytes'])
        return {'samples': len(rows), 'peak_mib': peak['memory_bytes'] / 1048576,
                'peak_anon_mib': max(row['anon_bytes'] for row in rows) / 1048576,
                'peak_pss_mib': max(row['resident_pss_mib'] for row in rows),
                'peak_rss_mib': max(sum(process['rss_kib'] for process in row['processes']) for row in rows) / 1024,
                'peak_file_mib': max(row['file_bytes'] for row in rows) / 1048576,
                'minimum_mib': min(row['memory_bytes'] for row in rows) / 1048576,
                'last_mib': rows[-1]['memory_bytes'] / 1048576,
                'cpu_usage_seconds': (rows[-1]['cpu']['usage_usec'] - rows[0]['cpu']['usage_usec']) / 1e6,
                'cpu_throttled_seconds': (rows[-1]['cpu']['throttled_usec'] - rows[0]['cpu']['throttled_usec']) / 1e6,
                'oom_kills_delta': rows[-1]['events']['oom_kill'] - rows[0]['events']['oom_kill'],
                'memory_events_end': rows[-1]['events'], 'processes_at_peak': peak['processes']}


def latency(results):
    values = sorted(result['seconds'] for result in results)
    if not values:
        return {'count': 0}
    return {'count': len(values), 'errors': sum(result['status'] != 200 for result in results),
            'p50_seconds': statistics.median(values), 'p95_seconds': values[max(0, math.ceil(.95 * len(values)) - 1)],
            'maximum_seconds': max(values), 'statuses': {str(code): sum(result['status'] == code for result in results) for code in {row['status'] for row in results}}}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('cgroup')
    parser.add_argument('name')
    parser.add_argument('--cases', default='')
    parser.add_argument('--mode', choices=['matrix', 'browse', 'combined', 'soak', 'probe', 'burst'], default='matrix')
    parser.add_argument('--seconds', type=int, default=300)
    parser.add_argument('--max-heavy', type=int, default=0)
    args = parser.parse_args()
    output = ROOT / 'results' / args.name
    output.mkdir(parents=True, exist_ok=True)
    monitor = Monitor(args.cgroup, output)
    report = {'name': args.name, 'mode': args.mode, 'started_at': time.time(), 'cgroup': args.cgroup,
              'limits': {key: (monitor.cgroup / key).read_text().strip() for key in ['memory.max', 'memory.swap.max', 'cpu.max']}, 'phases': []}
    report['server_config'] = {'projects': CONTEXT['project_count'],
                               'php_fpm_children': int(next(line.split('=', 1)[1] for line in (ROOT / 'rootfs/app/benchmark/php-fpm.conf').read_text().splitlines() if line.startswith('pm.max_children='))),
                               'php_worker_memory_limit_mib': 256, 'mysql_buffer_pool_mib': 128,
                               'pdf_queue_workers': 1, 'default_queue_workers': 1, 'persistent_libreoffice': True}
    sockets = []
    live_browser_results = []
    live_heavy_results = []
    heartbeat_stop = threading.Event()
    heartbeat_errors = []
    try:
        for index in range(20):
            connection = socket.create_connection(('127.0.0.1', 8080), timeout=10)
            connection.sendall(b'GET /app/capacitykey?protocol=7&client=js&version=8.6.0 HTTP/1.1\r\nHost: localhost:8080\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n')
            response = connection.recv(4096)
            assert b'101 Switching Protocols' in response, response
            sockets.append(connection)
        report['reverb_connections'] = len(sockets)
        def heartbeat():
            while not heartbeat_stop.wait(20):
                latest = monitor.rows[-1] if monitor.rows else {}
                (output / 'progress.json').write_text(json.dumps({'elapsed_seconds': round(time.time() - report['started_at']),
                    'phase': monitor.phase, 'browsing': latency(list(live_browser_results)),
                    'heavy_completed': len(live_heavy_results), 'heavy_errors': sum(row['status'] != 200 for row in live_heavy_results),
                    'memory_mib': round(latest.get('memory_bytes', 0) / 1048576, 1),
                    'resident_pss_mib': round(latest.get('resident_pss_mib', 0), 1),
                    'oom_kills': latest.get('events', {}).get('oom_kill'), 'websocket_errors': len(heartbeat_errors)}))
                payload = b'{"event":"pusher:ping","data":{}}'
                for connection in sockets:
                    try:
                        mask = os.urandom(4)
                        connection.sendall(bytes([0x81, 0x80 | len(payload)]) + mask + bytes(value ^ mask[index % 4] for index, value in enumerate(payload)))
                        connection.settimeout(.01)
                        try:
                            connection.recv(8192)
                        except socket.timeout:
                            pass
                    except OSError as error:
                        heartbeat_errors.append(str(error))
        threading.Thread(target=heartbeat, daemon=True).start()
        clients = {role: Client(role) for role in ['head', 'faculty', 'coordinator']}
        def phase(name, operation):
            monitor.phase = name
            start = time.monotonic()
            print(json.dumps({'phase': name, 'event': 'start'}), flush=True)
            result = operation()
            time.sleep(.5)
            entry = {'phase': name, 'seconds': time.monotonic() - start, 'result': result, 'resources': monitor.summarize(name)}
            report['phases'].append(entry)
            (output / 'report.json').write_text(json.dumps(report, indent=2))
            print(json.dumps({'phase': name, 'event': 'end', 'seconds': round(entry['seconds'], 2), 'peak_mib': round(entry['resources'].get('peak_mib', 0), 1), 'result': {key: value for key, value in result.items() if key in ['status', 'errors', 'p50_seconds', 'p95_seconds', 'case', 'count']}}), flush=True)
            return result

        phase('idle', lambda: (time.sleep(15) or {'ok': True}))
        if args.mode == 'matrix':
            cases = args.cases.split(',') if args.cases else ['native-seven-cold', 'native-seven', 'proposal-large', 'progress-images', 'terminal-images-tables', 'quarterly-evidence', 'expense-100', 'rms-500', 'rms-1500', 'image-preview-12mp', 'image-preview-20mp', 'image-guard', 'literature-200', 'literature-dense-200', 'assistant-pdf-200', 'assistant-docx-5m', 'assistant-docx-12m', 'assistant-vision-2pages', 'signed-form-text', 'signed-form-scan', 'manual-1000pages', 'manual-ocr-json', 'manual-long-paragraph']
            for case in cases:
                phase(case, lambda case=case: clients['faculty'].task(case))
        paths = {'head': ['head_dashboard', 'head_analytics', 'head_proposals', 'head_projects', 'head_report_reviews'],
                 'faculty': ['faculty_dashboard', 'faculty_submissions', 'faculty_draft', 'faculty_draft_review'],
                 'coordinator': ['coordinator_dashboard', 'quarterly_docx', 'quarterly_print']}
        browse_stop = threading.Event()
        def browse_worker(index, count=8, end=None):
            role = ['head', 'faculty', 'coordinator'][index % 3]
            client = Client(role)
            results = []
            iteration = 0
            while not browse_stop.is_set() and ((end is not None and time.monotonic() < end) or (end is None and iteration < count)):
                key = paths[role][iteration % len(paths[role])]
                response = client.get(CONTEXT['paths'][key])
                response.pop('body')
                response['path_key'] = key
                results.append(response)
                live_browser_results.append(response)
                iteration += 1
                if end is not None:
                    time.sleep(.75)
            return results
        if args.mode == 'probe':
            for role, keys in paths.items():
                for key in keys:
                    def get(role=role, key=key):
                        response = clients[role].get(CONTEXT['paths'][key])
                        body = response.pop('body')
                        response['body_excerpt'] = body[:800].decode(errors='replace') if response['status'] != 200 else None
                        return response
                    phase(key, get)
            phase('rms_pdf', lambda: {key: value for key, value in clients['head'].get(CONTEXT['paths']['rms_pdf']).items() if key != 'body'})
            phase('queue-request', lambda: clients['faculty'].task('queue-request'))
            phase('queue-wait', lambda: wait_queue(clients['faculty']))
        if args.mode == 'browse':
            for concurrency in [1, 3, 5, 10]:
                def browse(concurrency=concurrency):
                    with concurrent.futures.ThreadPoolExecutor(concurrency) as pool:
                        rows = sum(list(pool.map(browse_worker, range(concurrency))), [])
                    return {**latency(rows), 'requests': rows}
                phase('browse-' + str(concurrency), browse)
        if args.mode == 'burst':
            def burst():
                cases = args.cases.split(',') if args.cases else ['progress-highres-12', 'terminal-images-tables', 'rms-500']
                with concurrent.futures.ThreadPoolExecutor(6) as pool:
                    browsing = [pool.submit(browse_worker, index, count=12) for index in range(3)]
                    heavy = [pool.submit(lambda case=case: Client('faculty').task(case)) for case in cases]
                    rows = sum([future.result() for future in browsing], [])
                    outputs = [future.result() for future in heavy]
                return {**latency(rows), 'requests': rows, 'heavy': outputs}
            phase('burst-three-exports', burst)
        if args.mode in ['combined', 'soak']:
            def combined():
                deadline = time.monotonic() + args.seconds
                heavy = []
                with concurrent.futures.ThreadPoolExecutor(6) as pool:
                    browsing = [pool.submit(browse_worker, index, end=deadline) for index in range(3)]
                    cases = args.cases.split(',') if args.cases else ['progress-highres-12', 'terminal-images-tables', 'rms-500', 'manual-ocr-json', 'assistant-vision-2pages', 'proposal-large']
                    iteration = 0
                    while time.monotonic() < deadline and (args.max_heavy == 0 or len(heavy) < args.max_heavy):
                        case = cases[iteration % len(cases)]
                        result = clients['faculty'].task(case)
                        heavy.append({'case': case, **result})
                        live_heavy_results.append({'case': case, **result})
                        print(json.dumps({'event': 'heavy_complete', 'case': case, 'status': result['status'], 'seconds': round(result['seconds'], 2), 'completed': len(heavy), 'remaining_seconds': round(max(0, deadline - time.monotonic()))}), flush=True)
                        iteration += 1
                        if result['status'] == 0:
                            break
                        time.sleep(1)
                    browse_stop.set()
                    rows = sum([future.result() for future in browsing], [])
                return {**latency(rows), 'requests': rows, 'heavy': heavy}
            phase(args.mode, combined)
        phase('after-idle', lambda: (time.sleep(15) or {'ok': True}))
    except Exception as error:
        report['fatal'] = repr(error)
        raise
    finally:
        heartbeat_stop.set()
        for connection in sockets:
            connection.close()
        monitor.running = False
        monitor.thread.join(5)
        report['finished_at'] = time.time()
        report['reverb_heartbeat_errors'] = heartbeat_errors
        report['memory_events_end'] = monitor.read_map('memory.events')
        report['cgroup_peak_mib'] = int((monitor.cgroup / 'memory.peak').read_text()) / 1048576
        (output / 'report.json').write_text(json.dumps(report, indent=2))


def wait_queue(client):
    for attempt in range(300):
        result = client.task('queue-status')
        state = result.get('result', {}).get('state') or {}
        if state.get('status') in ['ready', 'failed']:
            return result
        time.sleep(2)
    return {'status': 0, 'timeout': 'queue did not finish within 600 seconds'}


if __name__ == '__main__':
    main()
