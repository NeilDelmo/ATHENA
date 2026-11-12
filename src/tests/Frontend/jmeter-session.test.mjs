import assert from 'node:assert/strict';
import test from 'node:test';
import { createServer } from 'node:http';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import * as capture from '../../scripts/jmeter/capture-session.mjs';

test('capture reuses a valid session and prompts only after it expires', async () => {
    assert.equal(typeof capture.captureSession, 'function', 'capture must support saved-session reuse');
    const directory = await mkdtemp(join(tmpdir(), 'athena-session-'));
    const destination = join(directory, 'head.json');
    let unavailable = false;
    const server = createServer((request, response) => {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        if (unavailable) {
            response.writeHead(503);
            response.end('Temporarily unavailable');
            return;
        }
        const authenticated = request.headers.cookie?.includes('athena_session=valid');
        if (request.url === '/choose-workspace' && authenticated) {
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end('{}');
        } else if (request.url === '/research-head/dashboard' && authenticated) {
            response.end('<meta name="csrf-token" content="test"><body data-active-workspace="research_head" data-auth-user-id="1"><nav data-research-head-navigation></nav></body>');
        } else if (request.url === '/login') {
            response.end('<a href="/auth/google">Sign in</a>');
        } else {
            response.writeHead(302, { Location: '/login' });
            response.end();
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const target = `http://127.0.0.1:${server.address().port}`;
    let browser;
    let prompts = 0;
    try {
        browser = await chromium.launch({ headless: true });
        const prompt = async page => {
            prompts++;
            await page.context().addCookies([{ name: 'athena_session', value: 'valid', url: target }]);
            await page.goto(`${target}/research-head/dashboard`);
        };
        const options = { workspace: 'research_head', target, destination, prompt };
        await capture.captureSession(browser, options);
        assert.equal(prompts, 1, 'first capture requires sign-in');
        await capture.captureSession(browser, options);
        assert.equal(prompts, 1, 'second capture reuses the authenticated state');
        const saved = JSON.parse(await readFile(destination, 'utf8'));
        assert.deepEqual(saved.origins, []);
        unavailable = true;
        await assert.rejects(capture.captureSession(browser, options), /HTTP 503/);
        assert.equal(prompts, 1, 'a server outage must not cause another login prompt');
        assert.deepEqual(JSON.parse(await readFile(destination, 'utf8')), saved);
        unavailable = false;
        saved.cookies.find(cookie => cookie.name === 'athena_session').value = 'expired';
        await writeFile(destination, JSON.stringify(saved));
        await capture.captureSession(browser, options);
        assert.equal(prompts, 2, 'server rejection requires fresh sign-in');
    } finally {
        await browser?.close();
        await new Promise(resolve => server.close(resolve));
        await rm(directory, { recursive: true, force: true });
    }
});
