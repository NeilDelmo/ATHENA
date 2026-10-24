import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';
import postcss from 'postcss';

test('shared styles suppress native disclosure triangles across all application layouts', () => {
    const css = postcss.parse(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'));
    const declarations = new Map();
    css.walkRules(rule => {
        if (['summary', 'summary::marker', 'summary::-webkit-details-marker'].includes(rule.selector)) {
            rule.walkDecls(declaration => declarations.set(`${rule.selector}:${declaration.prop}`, declaration.value));
        }
    });
    assert.equal(declarations.get('summary:list-style'), 'none');
    assert.equal(declarations.get('summary::marker:content'), "''");
    assert.equal(declarations.get('summary::-webkit-details-marker:display'), 'none');
    for (const view of ['layouts/app', 'layouts/guest', 'layouts/revision-editor', 'errors/layout', 'welcome']) {
        const source = readFileSync(new URL(`../../resources/views/${view}.blade.php`, import.meta.url), 'utf8');
        assert.ok(source.includes('resources/css/app.css'), `${view} must load the shared disclosure styles`);
    }
});

test('every disclosure in the views has an explicit visual cue after removing native triangles', () => {
    const root = new URL('../../resources/views/', import.meta.url);
    let summaries = 0;
    const missingCues = [];
    for (const path of readdirSync(root, { recursive: true }).filter(path => path.endsWith('.blade.php'))) {
        const source = readFileSync(new URL(path.replaceAll('\\', '/'), root), 'utf8')
            .replace(/<(\/?){{[^}]*'summary'[^}]*}}/g, '<$1summary');
        for (const [summary] of source.matchAll(/<summary\b[\s\S]*?<\/summary>/g)) {
            summaries++;
            if (!/disclosure-summary|<svg\b|&minus;|>›<\/span>/.test(summary) && !(summary.includes('Show history') && summary.includes('Hide history'))) missingCues.push(path);
        }
        assert.equal(/[▶▼▸▾►▽△▲◀◂]/.test(source), false, `${path} must not use a triangle glyph as a disclosure cue`);
    }
    assert.ok(summaries >= 20, 'The audit must cover application views, not just the chatbot');
    assert.deepEqual(missingCues, [], 'Every disclosure needs a visible cue');
});
