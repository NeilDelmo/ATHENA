import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { cpSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { createConnection, createServer } from 'node:net';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { roleCounts } from './mixed.mjs';

const app = fileURLToPath(new URL('../../', import.meta.url)).replaceAll('\\', '/').replace(/\/$/, '');
const id = Date.now();
const directory = `${app}/test-results/jmeter-local/${id}`;
const storage = `${directory}/storage`;
const cache = `test-results/jmeter-local/${id}/storage/bootstrap`;
const mysqlHome = process.env.ATHENA_JMETER_MYSQL_HOME ?? 'C:/laragon/bin/mysql/mysql-8.0.30-winx64';
const mysqlBinary = resolve(mysqlHome, 'bin/mysqld.exe');
const mysqlData = resolve(process.env.TEMP ?? process.env.TMP ?? directory, `athena-jmeter-mysql-${id}`);
const freePort = () => new Promise((resolvePort, rejectPort) => {
    const server = createServer();
    server.once('error', rejectPort);
    server.listen(0, '127.0.0.1', () => {
        const { port } = server.address();
        server.close(error => error ? rejectPort(error) : resolvePort(port));
    });
});
const [mysqlPort, appPort] = await Promise.all([freePort(), freePort()]);
const env = {
    ...process.env,
    APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${appPort}`, APP_DEBUG: 'false',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`, APP_PREVIOUS_KEYS: '',
    LARAVEL_STORAGE_PATH: resolve(storage), APP_CONFIG_CACHE: `${cache}/config.php`,
    APP_ROUTES_CACHE: `${cache}/routes.php`, APP_EVENTS_CACHE: `${cache}/events.php`,
    APP_SERVICES_CACHE: `${cache}/services.php`, APP_PACKAGES_CACHE: `${cache}/packages.php`,
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: String(mysqlPort), DB_DATABASE: `athena_jmeter_${id}`,
    DB_USERNAME: 'root', DB_PASSWORD: '', DB_URL: '', DB_SOCKET: '', DB_QUEUE_CONNECTION: 'mysql',
    SESSION_DRIVER: 'file', SESSION_COOKIE: 'athena_jmeter_session', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', FILESYSTEM_DISK: 'local', MAIL_MAILER: 'array',
    BROADCAST_CONNECTION: 'null', BCRYPT_ROUNDS: '4', RESEARCH_ASSISTANT_MANUAL_ENABLED: 'false',
    PHP_FCGI_MAX_REQUESTS: '20000',
};
for (const name of ['app/private', 'app/public', 'bootstrap', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) {
    mkdirSync(resolve(storage, name), { recursive: true });
}
cpSync(resolve(app, 'bootstrap/cache/packages.php'), `${cache}/packages.php`);
cpSync(resolve(app, 'bootstrap/cache/services.php'), `${cache}/services.php`);
const children = [];
let stopping = false;
function stop(code = 0) {
    if (stopping) return;
    stopping = true;
    for (const child of children) {
        if (process.platform === 'win32') {
            spawnSync('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], { stdio: 'ignore', windowsHide: true });
        } else {
            child.kill();
        }
    }
    process.exit(code);
}
function start(command, args, environment = env) {
    const child = spawn(command, args, { cwd: app, env: environment, stdio: ['ignore', 'inherit', 'inherit'], windowsHide: true });
    children.push(child);
    child.on('error', error => { console.error(error.message); stop(1); });
    child.on('exit', code => { if (!stopping) stop(code || 1); });
}
if (!existsSync(mysqlBinary)) throw new Error(`MySQL was not found at ${mysqlBinary}; set ATHENA_JMETER_MYSQL_HOME to its installation folder.`);
mkdirSync(mysqlData, { recursive: true });
const initialized = spawnSync(mysqlBinary, ['--no-defaults', `--basedir=${mysqlHome}`, `--datadir=${mysqlData}`, '--initialize-insecure'], { cwd: app, stdio: 'inherit', windowsHide: true });
if (initialized.status !== 0) throw new Error('Could not initialize the isolated JMeter MySQL data directory.');
start(mysqlBinary, ['--no-defaults', `--basedir=${mysqlHome}`, `--datadir=${mysqlData}`, `--port=${mysqlPort}`, '--bind-address=127.0.0.1', '--mysqlx=0', `--log-error=${mysqlData}/mysql.log`, `--pid-file=${mysqlData}/mysql.pid`]);
await new Promise((resolveReady, rejectReady) => {
    const deadline = Date.now() + 30_000;
    const check = () => {
        const socket = createConnection({ host: '127.0.0.1', port: mysqlPort });
        socket.setTimeout(500);
        socket.once('connect', () => { socket.destroy(); resolveReady(); });
        const retry = () => {
            socket.destroy();
            if (Date.now() >= deadline) rejectReady(new Error(`Isolated JMeter MySQL did not start on 127.0.0.1:${mysqlPort}.`));
            else setTimeout(check, 500);
        };
        socket.once('error', retry);
        socket.once('timeout', retry);
    };
    check();
});
const runPhp = args => spawnSync('php', ['scripts/jmeter/seed-local.php', ...args], { cwd: app, env, stdio: 'inherit', windowsHide: true });
const result = runPhp([JSON.stringify(roleCounts(100))]);
if (result.status !== 0 || !existsSync(`${directory}/pool.local.json`)) stop(1);
const runtimeEnvironment = Object.fromEntries(['APP_ENV', 'APP_KEY', 'APP_PREVIOUS_KEYS', 'APP_DEBUG', 'APP_URL', 'LARAVEL_STORAGE_PATH', 'APP_CONFIG_CACHE',
    'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'DB_CONNECTION', 'DB_HOST',
    'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DB_SOCKET', 'SESSION_DRIVER', 'SESSION_COOKIE',
    'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'CACHE_STORE', 'QUEUE_CONNECTION', 'MAIL_MAILER', 'FILESYSTEM_DISK', 'BCRYPT_ROUNDS',
].map(key => [key, env[key]]));
writeFileSync(`${directory}/runtime-env.local.json`, JSON.stringify(runtimeEnvironment, null, 2) + '\n', { mode: 0o600 });

writeFileSync(`${directory}/probe.php`, `<?php
require '${app}/vendor/autoload.php';
$app = require '${app}/bootstrap/app.php';
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$name = config('session.cookie');
$raw = $_COOKIE[$name] ?? null;
$decoded = $raw ? app('encrypter')->decrypt($raw, false) : null;
$id = $decoded ? \\Illuminate\\Cookie\\CookieValuePrefix::validate($name, $decoded, app('encrypter')->getAllKeys()) : null;
$file = config('session.files').'/'.$id;
$store = app('session.store');
if ($id && is_file($file)) { $store->setId($id); $store->start(); }
$guard = \\Illuminate\\Support\\Facades\\Auth::guard('web');
header('Content-Type: application/json');
echo json_encode(['cookieHeaderPresent' => isset($_SERVER['HTTP_COOKIE']), 'cookieParsed' => $raw !== null,
    'keyMatchesCookie' => $id !== null, 'sessionFileFound' => is_string($id) && is_file($file),
    'storedAuthenticationFound' => $id !== null && $store->get($guard->getName()) !== null,
    'testingEnvironment' => $app->environment('testing')]);
`);

const ports = Array.from({ length: 8 }, (_, i) => 8193 + i);
const config = `${directory}/nginx.conf`;
for (const name of ['logs', 'temp']) mkdirSync(`${directory}/${name}`);
writeFileSync(config, `worker_processes 1;
daemon off;
pid "${directory}/nginx.pid";
error_log stderr;
events { worker_connections 1024; }
http {
    access_log off;
    upstream athena_php { least_conn; ${ports.map(port => `server 127.0.0.1:${port};`).join(' ')} }
    server {
        listen 127.0.0.1:${appPort};
        root "${app}/public";
        location = /__jmeter/session {
            fastcgi_pass athena_php;
            fastcgi_param SCRIPT_FILENAME "${directory}/probe.php";
            fastcgi_param SCRIPT_NAME /__jmeter/session;
            fastcgi_param REQUEST_METHOD $request_method;
            fastcgi_param CONTENT_TYPE $content_type;
            fastcgi_param CONTENT_LENGTH $content_length;
            fastcgi_param HTTP_COOKIE $http_cookie;
            fastcgi_param REQUEST_URI $request_uri;
            fastcgi_param SERVER_PROTOCOL $server_protocol;
            fastcgi_param SERVER_PORT $server_port;
            fastcgi_param SERVER_NAME $server_name;
            fastcgi_param REMOTE_ADDR $remote_addr;
            fastcgi_param REDIRECT_STATUS 200;
        }
        location / { try_files $uri @laravel; }
        location @laravel {
            fastcgi_pass athena_php;
            fastcgi_next_upstream off;
            fastcgi_read_timeout 120s;
            fastcgi_param SCRIPT_FILENAME "${app}/public/index.php";
            fastcgi_param SCRIPT_NAME /index.php;
            fastcgi_param DOCUMENT_ROOT "${app}/public";
            fastcgi_param QUERY_STRING $query_string;
            fastcgi_param REQUEST_METHOD $request_method;
            fastcgi_param CONTENT_TYPE $content_type;
            fastcgi_param CONTENT_LENGTH $content_length;
            fastcgi_param HTTP_COOKIE $http_cookie;
            fastcgi_param HTTP_ACCEPT $http_accept;
            fastcgi_param HTTP_USER_AGENT $http_user_agent;
            fastcgi_param HTTP_REFERER $http_referer;
            fastcgi_param HTTP_ORIGIN $http_origin;
            fastcgi_param HTTP_X_CSRF_TOKEN $http_x_csrf_token;
            fastcgi_param HTTP_X_LIVEWIRE $http_x_livewire;
            fastcgi_param HTTP_X_REQUESTED_WITH $http_x_requested_with;
            fastcgi_param REQUEST_URI $request_uri;
            fastcgi_param SERVER_PROTOCOL $server_protocol;
            fastcgi_param SERVER_PORT $server_port;
            fastcgi_param SERVER_NAME $server_name;
            fastcgi_param REMOTE_ADDR $remote_addr;
            fastcgi_param REDIRECT_STATUS 200;
        }
    }
}
`);

for (const port of ports) {
    // Windows can lock a compiled Blade file while another worker is replacing it.
    const views = resolve(storage, `framework/views-${port}`);
    cpSync(resolve(storage, 'framework/views'), views, { recursive: true });
    start('php-cgi', ['-d', 'opcache.enable=1', '-b', `127.0.0.1:${port}`], { ...env, VIEW_COMPILED_PATH: views });
}
start('nginx', ['-p', `${directory}/`, '-c', config]);
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => stop());

console.log(`Local pool: ${directory}/pool.local.json`);
console.log('Enter verify after the submission and review run to check saved report status.');
process.stdin.setEncoding('utf8');
process.stdin.on('data', data => {
    if (data.trim() === 'verify') console.log(`Verification exit code: ${runPhp(['verify']).status}`);
    if (data.trim() === 'stop') stop();
});
