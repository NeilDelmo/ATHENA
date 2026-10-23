import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

function storage(initial = {}) {
    const values = new Map(Object.entries(initial));
    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
    };
}

function library(localStorage, key = 'athena-report-library', requestFrame = callback => callback()) {
    const source = readFileSync(new URL('../../resources/js/report-library.js', import.meta.url), 'utf8')
        .replace('export function ', 'function ');
    const factory = runInNewContext(`${source}\nreportLibrary`, { localStorage, window: { localStorage, innerWidth: 1024, innerHeight: 768, requestAnimationFrame: requestFrame } });
    const state = factory(key);
    state.init();
    return state;
}

test('report layout preferences persist together across instances at their own storage key', () => {
    const localStorage = storage();
    const state = library(localStorage);
    assert.equal(state.view, 'grid');
    assert.equal(state.size, 'medium');

    state.setPreference('view', 'list');
    assert.deepEqual(JSON.parse(localStorage.getItem('athena-report-library')), { view: 'list', size: 'medium' });
    state.setPreference('size', 'large');
    const restored = library(localStorage);
    assert.equal(restored.view, 'list');
    assert.equal(restored.size, 'large');
    restored.setPreference('size', 'small');
    assert.equal(library(localStorage).size, 'small');

    const separate = library(localStorage, 'athena-other-library');
    assert.equal(separate.view, 'grid');
    assert.equal(separate.size, 'medium');
});

test('malformed and unknown saved preferences fall back to usable defaults', () => {
    for (const value of ['{bad json', 'null', '[]', 'true', '42', '"text"', '{"view":"rows","size":"tiny"}', '{"view":null,"size":2}']) {
        const state = library(storage({ 'athena-report-library': value }));
        assert.equal(state.view, 'grid', value);
        assert.equal(state.size, 'medium', value);
    }

    const validView = library(storage({ 'athena-report-library': '{"view":"list","size":"tiny"}' }));
    assert.equal(validView.view, 'list');
    assert.equal(validView.size, 'medium');
    const validSize = library(storage({ 'athena-report-library': '{"view":"rows","size":"large"}' }));
    assert.equal(validSize.view, 'grid');
    assert.equal(validSize.size, 'large');
});

test('unsupported preference keys and values cannot change or overwrite saved layout', () => {
    const localStorage = storage({ 'athena-report-library': '{"view":"list","size":"large"}' });
    const state = library(localStorage);
    for (const [key, value] of [['view', 'rows'], ['size', 'tiny'], ['grouping', 'year'], ['__proto__', 'grid']]) {
        state.setPreference(key, value);
    }
    assert.equal(state.view, 'list');
    assert.equal(state.size, 'large');
    assert.deepEqual(JSON.parse(localStorage.getItem('athena-report-library')), { view: 'list', size: 'large' });
});

test('blocked storage reads leave layout controls usable', () => {
    const state = library({
        getItem() { throw new Error('Storage is blocked'); },
        setItem() { throw new Error('Storage is blocked'); },
    });
    assert.equal(state.view, 'grid');
    assert.equal(state.size, 'medium');
    state.setPreference('view', 'list');
    state.setPreference('size', 'small');
    assert.equal(state.view, 'list');
    assert.equal(state.size, 'small');
});

test('blocked storage writes keep the selected layout active for the current page', () => {
    const state = library({
        getItem: () => '{"view":"list","size":"small"}',
        setItem() { throw new Error('Storage quota exceeded'); },
    });
    state.setPreference('size', 'large');
    state.setPreference('view', 'grid');
    assert.equal(state.view, 'grid');
    assert.equal(state.size, 'large');
});

function wheel(deltaY, overrides = {}) {
    return { ctrlKey: true, deltaY, deltaX: 0, deltaMode: 0, prevented: false,
        preventDefault() { this.prevented = true; }, ...overrides };
}

test('Ctrl wheel cycles icon sizes and Details without zooming the page and remembers the choice', () => {
    const localStorage = storage();
    const state = library(localStorage);
    const up = wheel(-120);
    state.resizeWithWheel(up);
    assert.equal(up.prevented, true);
    assert.equal(state.size, 'large');
    state.resizeWithWheel(wheel(-120));
    assert.equal(state.size, 'large');
    state.resizeWithWheel(wheel(120));
    assert.equal(state.size, 'medium');
    state.resizeWithWheel(wheel(120));
    assert.equal(state.size, 'small');
    state.resizeWithWheel(wheel(120));
    assert.equal(state.view, 'list');
    state.resizeWithWheel(wheel(120));
    assert.equal(state.view, 'list');
    state.resizeWithWheel(wheel(-120));
    assert.equal(state.view, 'grid');
    assert.equal(state.size, 'small');
    assert.equal(library(localStorage).size, 'small');
    assert.equal(library(localStorage).view, 'grid');
});

test('normal scrolling and editable fields keep native wheel behavior while trackpad deltas accumulate', () => {
    const state = library(storage());
    for (const event of [wheel(-120, { ctrlKey: false }), wheel(-120, { shiftKey: true }),
        wheel(-10, { deltaX: 80 }), wheel(-120, { target: { closest: () => ({}) } }), wheel(0)]) {
        state.resizeWithWheel(event);
        assert.equal(event.prevented, false);
        assert.equal(state.size, 'medium');
    }
    state.resizeWithWheel(wheel(-20));
    assert.equal(state.size, 'medium');
    state.resizeWithWheel(wheel(-20));
    assert.equal(state.size, 'large');
    state.resizeWithWheel(wheel(3, { deltaMode: 1 }));
    assert.equal(state.size, 'medium');
    state.resizeWithWheel(wheel(1, { deltaMode: 2 }));
    assert.equal(state.size, 'small');
});

test('View menu is clamped to the viewport, supports keyboard navigation and returns focus', () => {
    const state = library(storage());
    let focused = null;
    const items = ['small', 'medium', 'large', 'list'].map(value => ({ focus() { focused = value; } }));
    const menu = { getBoundingClientRect: () => ({ width: 240, height: 220 }),
        querySelector: () => items[1], querySelectorAll: () => items };
    const surface = { focus() { focused = 'surface'; }, getBoundingClientRect: () => ({ left: 36, bottom: 120 }) };
    state.$refs = { libraryViewMenu: menu };
    state.$nextTick = callback => callback();
    let prevented = false;
    state.openViewMenu({ target: surface, currentTarget: surface, clientX: 1010, clientY: 755, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.viewMenuOpen, true);
    assert.equal(state.viewMenuX, 776);
    assert.equal(state.viewMenuY, 540);
    assert.equal(focused, 'medium');
    state.handleViewMenuKey({ key: 'ArrowDown', target: items[1], currentTarget: menu, preventDefault() {} });
    assert.equal(focused, 'large');
    state.handleViewMenuKey({ key: 'Home', target: items[2], currentTarget: menu, preventDefault() {} });
    assert.equal(focused, 'small');
    state.handleViewMenuKey({ key: 'Escape', preventDefault() {} });
    assert.equal(state.viewMenuOpen, false);
    assert.equal(focused, 'surface');
    state.openViewMenu({ target: surface, currentTarget: surface, clientX: 0, clientY: 0, preventDefault() {} });
    assert.equal(state.viewMenuX, 36);
    assert.equal(state.viewMenuY, 120);
    state.handleViewMenuKey({ key: 'Tab', preventDefault() { assert.fail('native Tab navigation was blocked'); } });
    assert.equal(state.viewMenuOpen, false);
    assert.equal(focused, 'surface');
    state.openViewMenu({ target: surface, currentTarget: surface, clientX: 0, clientY: 0, preventDefault() {} });
    state.chooseViewOption('list');
    assert.equal(state.view, 'list');
    assert.equal(state.viewMenuOpen, false);
    assert.equal(focused, 'surface');
    state.chooseViewOption('large');
    assert.equal(state.view, 'grid');
    assert.equal(state.size, 'large');
    state.chooseViewOption('tiny');
    assert.equal(state.size, 'large');
});

test('View menu waits for its visible frame before measuring and never focuses a closed menu', () => {
    const frames = [];
    const state = library(storage(), undefined, callback => frames.push(callback));
    let visible = false;
    let measured = false;
    let focused = false;
    state.$nextTick = callback => callback();
    state.$refs = { libraryViewMenu: {
        getBoundingClientRect() { measured = true; return visible ? { width: 240, height: 220 } : { width: 0, height: 0 }; },
        querySelector: () => ({ focus() { focused = true; } }),
    } };
    const surface = { getBoundingClientRect: () => ({ left: 36, bottom: 1200 }) };
    state.openViewMenu({ target: surface, currentTarget: surface, clientX: 0, clientY: 0, preventDefault() {} });
    assert.equal(measured, false);
    visible = true;
    frames.shift()();
    assert.equal(state.viewMenuY, 540);
    assert.equal(focused, true);
    focused = false;
    state.openViewMenu({ target: surface, currentTarget: surface, clientX: 0, clientY: 0, preventDefault() {} });
    state.closeViewMenu(false);
    frames.shift()();
    assert.equal(focused, false);
});

test('editable context menus remain native and dismissing outside does not steal focus', () => {
    const state = library(storage());
    state.openViewMenu({ target: { closest: () => ({}) }, preventDefault() { assert.fail('native menu was blocked'); } });
    assert.equal(state.viewMenuOpen, false);
    state.viewMenuOpen = true;
    state.closeViewMenu(false);
    assert.equal(state.viewMenuOpen, false);
});

test('busy and stale project workspaces cannot open a menu with disabled choices', () => {
    const state = library(storage());
    for (const status of ['saving', 'stale']) {
        state[status] = true;
        state.openViewMenu({ preventDefault() { assert.fail('unavailable menu opened'); } });
        assert.equal(state.viewMenuOpen, false);
        state[status] = false;
    }
});

test('file menus prepare the clicked selection and focus the first available action', () => {
    const state = library(storage());
    let focused = null;
    let prepared = false;
    state.menuActions = [{ id: 'open', label: 'Open', run() {} }];
    state.prepareContextMenu = () => { prepared = true; };
    state.$nextTick = callback => callback();
    state.$refs = { libraryViewMenu: {
        getBoundingClientRect: () => ({ width: 240, height: 220 }),
        querySelector: selector => ({ focus() { focused = selector; } }),
    } };
    const target = { getBoundingClientRect: () => ({ left: 20, bottom: 40 }) };
    state.openViewMenu({ target, currentTarget: target, preventDefault() {} });
    assert.equal(prepared, true);
    assert.match(focused, /menuitem/);
    assert.doesNotMatch(focused, /aria-checked/);
});

test('menu actions close before executing and disabled actions cannot run', () => {
    const state = library(storage());
    let calls = 0;
    const action = { id: 'paste', disabled: true, run() { calls++; assert.equal(state.viewMenuOpen, false); } };
    state.viewMenuOpen = true;
    state.activateMenuAction(action);
    assert.equal(calls, 0);
    assert.equal(state.viewMenuOpen, true);
    action.disabled = false;
    state.activateMenuAction(action);
    assert.equal(calls, 1);
});

test('document details include source uploader date and size without invalid metadata', () => {
    const state = library(storage());
    const details = state.documentDetails({ source: 'Submitted report', uploaded_by: 'Dr. Santos', uploaded_at: '2025-10-20T12:00:00Z', file_size: 24576 });
    assert.match(details, /Submitted report · Dr\. Santos/);
    assert.match(details, /2025/);
    assert.match(details, /24(?:\.0)? KB/);
    assert.equal(state.documentDetails({ source: 'Generated', uploaded_at: 'invalid', file_size: null }), 'Generated');
});
