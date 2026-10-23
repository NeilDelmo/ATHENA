import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import http from 'node:http';
import net from 'node:net';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import test from 'node:test';
import { gunzipSync } from 'node:zlib';

const apache = process.env.ATHENA_APACHE_EXECUTABLE || 'C:/laragon/bin/apache/Apache24/bin/httpd.exe';
const pathForApache = path => path.replaceAll('\\', '/');

function request(port, path, headers = {}) {
    return new Promise((resolve, reject) => {
        http.get({ hostname: '127.0.0.1', port, path, headers, timeout: 2000 }, response => {
            const chunks = [];
            response.on('data', chunk => chunks.push(chunk));
            response.on('end', () => resolve({ status: response.statusCode, headers: response.headers, body: Buffer.concat(chunks) }));
        }).on('timeout', function () { this.destroy(new Error('Asset request timed out')); }).on('error', reject);
    });
}

for (const compress of [false, true]) {
test(`Apache serves versioned assets safely through both URL layouts with compression ${compress ? 'enabled' : 'disabled'}`, {
    skip: !existsSync(apache) && 'Set ATHENA_APACHE_EXECUTABLE to run the real HTTP asset check', timeout: 30000,
}, async context => {
    const directory = mkdtempSync(join(tmpdir(), 'athena-static-assets-'));
    const apacheRoot = pathForApache(dirname(dirname(apache)));
    const root = join(directory, 'athena-app');
    const publicRoot = join(root, 'src', 'public');
    mkdirSync(join(publicRoot, 'build', 'assets'), { recursive: true });
    copyFileSync(resolve('../.htaccess'), join(root, '.htaccess'));
    copyFileSync(resolve('public/.htaccess'), join(publicRoot, '.htaccess'));
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const assets = ['resources/js/app.js', 'resources/css/app.css'].map(key => manifest[key].file);
    for (const asset of assets) copyFileSync(join('public/build', asset), join(publicRoot, 'build', asset));
    writeFileSync(join(publicRoot, 'build', 'manifest.json'), '{}');
    writeFileSync(join(publicRoot, 'plain.js'), 'window.unversioned = true;');
    writeFileSync(join(root, 'index.php'), 'Private application response');
    writeFileSync(join(publicRoot, 'index.php'), 'Private application response');
    const listener = net.createServer();
    listener.listen(0, '127.0.0.1');
    await once(listener, 'listening');
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const config = join(directory, 'httpd.conf');
    writeFileSync(config, `ServerRoot "${apacheRoot}"
Listen 127.0.0.1:${port}
ServerName 127.0.0.1
PidFile "${pathForApache(join(directory, 'httpd.pid'))}"
ErrorLog "${pathForApache(join(directory, 'error.log'))}"
${['authz_core', 'mime', 'alias', 'rewrite', 'headers', ...(compress ? ['deflate'] : [])].map(module => `LoadModule ${module}_module modules/mod_${module}.so`).join('\n')}
TypesConfig "${apacheRoot}/conf/mime.types"
DocumentRoot "${pathForApache(directory)}"
Alias /direct/ "${pathForApache(publicRoot)}/"
<Directory "${pathForApache(directory)}">
    AllowOverride All
    Require all granted
</Directory>
`);
    const syntax = spawnSync(apache, ['-t', '-f', config], { encoding: 'utf8', windowsHide: true, timeout: 5000 });
    assert.equal(syntax.status, 0, syntax.stderr);
    const server = spawn(apache, ['-X', '-f', config], { windowsHide: true, stdio: 'ignore' });
    try {
        for (let attempt = 0; ; attempt++) {
            try { await request(port, '/athena-app/build/manifest.json'); break; }
            catch (error) { if (attempt >= 40) throw error; await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        for (const prefix of ['/athena-app/', '/direct/']) {
            for (const asset of assets) {
                const compressed = await request(port, `${prefix}build/${asset}`, { 'Accept-Encoding': 'gzip' });
                const plain = await request(port, `${prefix}build/${asset}`, { 'Accept-Encoding': 'identity' });
                assert.equal(compressed.status, 200);
                assert.equal(compressed.headers['cache-control'], 'public, max-age=31536000, immutable');
                assert.equal(compressed.headers['content-encoding'], compress ? 'gzip' : undefined);
                if (compress) {
                    assert.match(compressed.headers.vary, /Accept-Encoding/);
                    assert.deepEqual(gunzipSync(compressed.body), plain.body);
                    assert.ok(compressed.body.length < plain.body.length / 2);
                } else {
                    assert.deepEqual(compressed.body, plain.body);
                }
                const unchanged = await request(port, `${prefix}build/${asset}`, { 'If-None-Match': plain.headers.etag });
                assert.equal(unchanged.status, 304);
                context.diagnostic(`${prefix}${asset}: ${plain.body.length} → ${compressed.body.length} bytes`);
            }
            for (const path of ['build/manifest.json', 'plain.js', 'private-page', 'private-AbCd1234.js', 'build/assets/missing-AbCd1234.js']) {
                const response = await request(port, `${prefix}${path}`);
                assert.equal(response.status, 200);
                assert.equal(response.headers['cache-control'], undefined, `${path} must not receive immutable public caching`);
            }
        }
    } finally {
        const stopped = once(server, 'exit');
        server.kill();
        await stopped;
        assert.ok(directory.startsWith(join(tmpdir(), 'athena-static-assets-')));
        rmSync(directory, { recursive: true, force: true });
    }
});
}
