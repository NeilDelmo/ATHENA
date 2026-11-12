"""Run the prepared ATHENA major-feature workload using Locust."""
import csv
import json
import math
import sys
import time
from collections import deque
from html.parser import HTMLParser
from pathlib import Path
from string import Template


class Page(HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.body = {}
        self.login = False
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'body':
            self.body = attrs
        if (tag == 'form' and attrs.get('action', '').endswith('/login')) or (tag == 'a' and attrs.get('href', '').endswith('/auth/google')):
            self.login = True


def response_error(status, content_type, content, operation, user):
    if status != 200:
        return f'Unexpected HTTP {status}'
    if operation.get('binary'):
        return 'Expected a nonempty document' if not content or 'html' in content_type.lower() else None
    page = Page(content.decode('utf-8', errors='replace'))
    if page.login:
        return 'Sign-in page returned'
    if page.body.get('data-active-workspace') != user.get('workspace'):
        return 'Wrong workspace'
    if user.get('actorId') and page.body.get('data-auth-user-id') != str(user['actorId']):
        return 'Wrong account'
    return None


def main(config_path):
    # Import only when running, so the response checks need no Locust runtime.
    from locust import HttpUser, constant, task
    import gevent
    from locust.env import Environment
    from locust.exception import StopUser

    config = json.loads(Path(config_path).read_text(encoding='utf-8'))
    print(f"Starting Locust: {len(config['users'])} users against {config['baseUrl']}", flush=True)
    for user in config['users']:
        scenario = user.get('scenario', config)
        if any(op.get('method', 'GET') != 'GET' or op.get('write') or op.get('external') for op in scenario['operations']):
            raise ValueError('Only read-only major-feature operations are supported')
    available = deque(config['users'])
    completed = set()
    coverage = set()
    failures = []
    measured_from = float('inf')
    measured_until = float('inf')
    samples = []
    timed = config.get('durationSeconds', 0) > 0

    class AthenaUser(HttpUser):
        host = config['baseUrl']
        wait_time = constant(0)

        def on_start(self):
            self.actor = available.popleft()
            self.scenario = self.actor.get('scenario', config)
            self.iteration = 0
            state = json.loads(Path(self.actor['sessionFile']).read_text(encoding='utf-8'))
            from urllib.parse import urlparse
            hostname = urlparse(self.host).hostname
            for cookie in state['cookies']:
                domain = cookie['domain'].lstrip('.')
                if hostname == domain or hostname.endswith('.' + domain):
                    self.client.cookies.set(cookie['name'], cookie['value'], domain=cookie['domain'], path=cookie.get('path', '/'), secure=cookie.get('secure', False))
            if not self.request_page({'id': 'session', 'path': self.scenario['dashboard']}, setup=True):
                raise StopUser()

        def request_page(self, op, setup=False, records=None):
            path = Template(op['path']).substitute(records or {})
            started = time.monotonic()
            name = self.actor.get('workspace', config.get('workspace')) + ' | ' + op['id']
            with self.client.get(path, name=name, allow_redirects=False, timeout=(10, 120), catch_response=True, headers={'User-Agent': 'ATHENA-Capstone-Locust'}) as response:
                actor = dict(self.actor, workspace=self.actor.get('workspace', config.get('workspace')))
                error = response_error(response.status_code, response.headers.get('Content-Type', ''), response.content, op, actor)
                if setup and not error and self.scenario['workspaceMarker'] not in response.text:
                    error = 'Missing workspace navigation'
                if error:
                    response.failure(error)
                    failures.append({'operation': name, 'error': error})
                if not setup and measured_from <= started < measured_until:
                    elapsed = (time.monotonic() - started) * 1000
                    samples.append((name, elapsed, bool(error)))
                    coverage.add((str(self.actor.get('actorId', self.actor['sessionFile'])), op['id']))
            return not error

        @task
        def journey(self):
            records = self.actor.get('recordSets') or [self.actor.get('records', {})]
            record = {**self.actor.get('records', {}), **records[self.iteration % len(records)]}
            for op in self.scenario['operations']:
                gevent.sleep(config['pauseMs'] / 1000)
                if timed and time.monotonic() >= measured_until:
                    raise StopUser()
                if not self.request_page(op, records=record) and not timed:
                    completed.add(id(self))
                    raise StopUser()
            self.iteration += 1
            if not timed and self.iteration >= config['loops']:
                completed.add(id(self))
                raise StopUser()

    environment = Environment(user_classes=[AthenaUser], stop_timeout=125)
    environment.events.user_error.add_listener(lambda **kwargs: failures.append({'error': 'Locust virtual user raised an exception'}))
    runner = environment.create_local_runner()
    runner.start(len(config['users']), spawn_rate=len(config['users']) / config['rampUpSeconds'])
    if timed:
        gevent.sleep(config['warmupSeconds'])
        environment.stats.reset_all()
        measured_from = time.monotonic()
        measurement_seconds = config['durationSeconds'] - config['warmupSeconds']
        measured_until = measured_from + measurement_seconds
        gevent.sleep(measurement_seconds)
    else:
        measured_from = time.monotonic()
        deadline = measured_from + 180
        while len(completed) < len(config['users']) and time.monotonic() < deadline and not failures:
            gevent.sleep(0.2)
        measured_until = time.monotonic()
        measurement_seconds = measured_until - measured_from
    print(f'Finishing requests: {len(samples)} samples, {len(failures)} errors', flush=True)
    runner.stop()
    runner.quit()
    required = sum(len(user.get('scenario', config)['operations']) for user in config['users'])
    times = sorted(sample[1] for sample in samples)
    summary = {'tool': 'Locust', 'baseUrl': config['baseUrl'], 'users': len(config['users']),
               'measurementSeconds': measurement_seconds, 'requests': len(samples),
               'failures': sum(sample[2] for sample in samples), 'setupOrRunErrors': len(failures),
               'averageMs': round(sum(times) / len(times), 2) if times else None,
               'p95Ms': round(times[math.ceil(len(times) * 0.95) - 1], 2) if times else None,
               'coveredUserOperations': len(coverage), 'requiredUserOperations': required}
    summary['passed'] = bool(samples) and not failures and len(coverage) == required
    output = Path(config_path).parent
    (output / 'locust-summary.json').write_text(json.dumps(summary, indent=2) + '\n')
    with (output / 'locust-requests.csv').open('w', newline='') as stream:
        writer = csv.writer(stream)
        writer.writerow(['operation', 'responseMs', 'failed'])
        writer.writerows(samples)
    (output / 'locust-errors.json').write_text(json.dumps(failures, indent=2) + '\n')
    print(json.dumps(summary), flush=True)
    return 0 if summary['passed'] else 1


if __name__ == '__main__':
    sys.exit(main(sys.argv[1]))
