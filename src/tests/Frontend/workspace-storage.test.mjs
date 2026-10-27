import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeWorkspaceSwitching, workspacePageState } from '../../resources/js/workspace-switching.js';

for (const saved of ['null', '[]', '"old data"', '42', '{broken']) {
    test(`workspace switching and page preferences recover from ${saved}`, context => {
        const key = 'athena-workspace-locations:4';
        const storage = { [key]: saved };
        Object.defineProperties(storage, {
            getItem: { value: name => storage[name] ?? null },
            setItem: { value: (name, value) => { storage[name] = value; } },
            removeItem: { value: name => { delete storage[name]; } },
        });
        const doc = {
            querySelector: selector => selector === '[data-auth-user-id]' ? { dataset: { authUserId: '4' } }
                : selector === '[data-active-workspace]' ? { dataset: { activeWorkspace: 'faculty' } } : null,
            addEventListener() {}, removeEventListener() {},
        };
        const browser = {
            sessionStorage: storage,
            location: { href: 'https://athena.test/projects?status=active#files', pathname: '/projects' },
            addEventListener() {}, removeEventListener() {},
        };
        const previous = { window: globalThis.window, document: globalThis.document, sessionStorage: globalThis.sessionStorage };
        Object.assign(globalThis, { window: browser, document: doc, sessionStorage: storage });
        context.after(() => Object.assign(globalThis, previous));

        const preferences = workspacePageState('records');
        preferences.write({ view: 'files' });
        assert.deepEqual(preferences.read(), { view: 'files' });

        storage[key] = saved;
        const cleanup = initializeWorkspaceSwitching(() => {}, doc, browser);
        assert.equal(JSON.parse(storage[key]).faculty.url, browser.location.href);
        cleanup();
    });
}
