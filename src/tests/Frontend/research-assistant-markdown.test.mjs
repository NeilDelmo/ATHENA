import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatAssistantMarkdown,
    stripAssistantGroundingFooter,
} from '../../resources/js/research-assistant-markdown.js';

test('assistant markdown renders standard chat typography', () => {
    const html = formatAssistantMarkdown(`### What to enter

Use **A4 bond paper**.

- Unit: ream
- Quantity: 10

1. Enter the item
2. Check the total`);

    assert.match(html, /<h4[^>]*>What to enter<\/h4>/);
    assert.doesNotMatch(html, /###/);
    assert.match(html, /<strong>A4 bond paper<\/strong>/);
    assert.match(html, /<ul[^>]*>.*<li[^>]*>Unit: ream<\/li>/);
    assert.match(html, /<ol[^>]*>.*<li[^>]*>Enter the item<\/li>/);
});

test('assistant markdown escapes unsafe model output and only links web URLs', () => {
    const html = formatAssistantMarkdown(`<script>alert("x")</script>

[Official guide](https://example.edu/guide)

[Unsafe](javascript:alert(1))`);

    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /href="https:\/\/example\.edu\/guide"/);
    assert.doesNotMatch(html, /href="javascript:/);
});

test('saved responses no longer repeat the separate athena sources footer', () => {
    const response = `Use ream for bond paper.

**Grounded with ATHENA knowledge**
**ATHENA 1 · Estimated Expense Breakdown — Unit**
**ATHENA 2 · Estimated Expense Breakdown field guide**`;

    assert.equal(stripAssistantGroundingFooter(response), 'Use ream for bond paper.');
});
