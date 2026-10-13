import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatAssistantMarkdown,
    stripAssistantGroundingFooter,
} from '../../resources/js/research-assistant-markdown.js';
import * as assistantMarkdown from '../../resources/js/research-assistant-markdown.js';

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
    assert.match(html, /<ul[^>]*>.*<li[^>]*><p[^>]*>Unit: ream<\/p><\/li>/);
    assert.match(html, /<ol[^>]*>.*<li[^>]*><p[^>]*>Enter the item<\/p><\/li>/);
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

test('nested lists keep their hierarchy, wrapped text, and paragraphs inside the correct item', () => {
    const html = formatAssistantMarkdown(`1. Check the objective
   against the methods.

   - **Sampling:** describe participants
     and recruitment.

     Keep consent separate.
     1. Explain eligibility
     2. Explain exclusions
   - Check the budget
2. Revise the work plan

The review is optional.`);

    assert.match(html, /<ol[^>]*><li[^>]*><p[^>]*>Check the objective against the methods\.<\/p><ul[^>]*><li[^>]*><p[^>]*><strong>Sampling:<\/strong> describe participants and recruitment\.<\/p><p[^>]*>Keep consent separate\.<\/p><ol[^>]*>/);
    assert.match(html, /Explain exclusions<\/p><\/li><\/ol><\/li><li[^>]*><p[^>]*>Check the budget<\/p><\/li><\/ul><\/li><li[^>]*><p[^>]*>Revise the work plan<\/p><\/li><\/ol><p[^>]*>The review is optional\.<\/p>/);
});

test('ordered lists retain their starting number and headings remain semantic', () => {
    const html = formatAssistantMarkdown('# Recommendation\n\n## Reason\n\n3. First step\n4. Next step');

    assert.match(html, /<h2[^>]*>Recommendation<\/h2>/);
    assert.match(html, /<h3[^>]*>Reason<\/h3>/);
    assert.match(html, /<ol[^>]*start="3"[^>]*>/);
});

test('only supplied source references become evidence controls', () => {
    const html = formatAssistantMarkdown('Use the saved objective [Detailed Proposal]. Unknown [ATHENA 9]. `Do not link [Detailed Proposal]` [Official guide](https://example.edu/guide)', [
        { reference: 'Detailed Proposal', title: 'Saved detailed proposal', url: 'https://athena.test/drafts/12/details' },
    ]);

    assert.match(html, /class="[^"]*assistant-evidence-link[^"]*"/);
    assert.match(html, /href="https:\/\/athena\.test\/drafts\/12\/details"/);
    assert.match(html, /Open evidence/);
    assert.match(html, /Unknown \[ATHENA 9\]/);
    assert.match(html, /<code[^>]*>Do not link \[Detailed Proposal\]<\/code>/);
    assert.equal((html.match(/assistant-evidence-link/g) || []).length, 1);
    assert.match(html, /href="https:\/\/example\.edu\/guide"/);
});

test('hostile URLs and evidence labels stay inert while safe query strings survive escaping', () => {
    const html = formatAssistantMarkdown('[Blocked](https://user:secret@example.edu) [Script](javascript:alert(1)) [Safe](https://example.edu/?a=1&b=2) [Unsafe evidence] [No URL]', [
        { reference: 'Unsafe evidence', title: '<img src=x onerror=alert(1)>', url: 'javascript:alert(1)' },
        { reference: 'No URL', title: 'Guide', url: null },
    ]);

    assert.doesNotMatch(html, /href="(?:javascript:|https:\/\/user:)/);
    assert.doesNotMatch(html, /<img/);
    assert.doesNotMatch(html, /assistant-evidence-link/);
    assert.match(html, /href="https:\/\/example\.edu\/\?a=1&amp;b=2"/);
    assert.match(html, /\[Unsafe evidence\] \[No URL\]/);
});

test('source URL validation rejects executable, relative, credential, and control-character URLs', () => {
    assert.equal(typeof assistantMarkdown.safeAssistantSourceUrl, 'function');
    for (const url of ['javascript:alert(1)', 'data:text/html,bad', '/papers/12', 'https://user:secret@example.edu', 'https://example.edu/\nattack', null]) {
        assert.equal(assistantMarkdown.safeAssistantSourceUrl(url), '');
    }
    assert.equal(assistantMarkdown.safeAssistantSourceUrl('https://athena.test/papers/12#objectives'), 'https://athena.test/papers/12#objectives');
});

test('structured reviews put suggested next steps first and collapse supporting text', () => {
    assert.equal(typeof assistantMarkdown.formatAssistantReview, 'function');
    const html = assistantMarkdown.formatAssistantReview({
        summary: 'One objective is not supported by the saved work plan.',
        record_basis: 'submitted_version', version_number: 2, reviewed_at: '2026-10-13T12:00:00Z',
        papers: [{ reference: 'Work Plan', title: 'Work plan v2', url: 'https://athena.test/papers/9', coverage: 'saved_structured_fields', omitted_fields: [], truncated_fields: [] }],
        findings: [{
            title: 'Objective 2 lacks an activity', status: 'not_evidenced', explanation: 'The saved activities cover Objective 1 only.',
            next_edit: 'Add an activity for Objective 2.',
            evidence: [{ reference: 'Work Plan', location: 'Activity 1', quote: 'Maintain the laboratory equipment.', url: 'https://athena.test/papers/9#activity-1' }],
        }],
        limitations: ['Uploaded images were not read.'],
    });

    assert.match(html, /<p[^>]*assistant-review-summary[^>]*>One objective/);
    assert.match(html, /<section[^>]*assistant-review-finding[^>]*>/);
    assert.match(html, /<h3[^>]*>Objective 2 lacks an activity<\/h3>/);
    assert.match(html, /Evidence missing/);
    assert.match(html, /<blockquote[^>]*>Maintain the laboratory equipment\.<\/blockquote>/);
    assert.match(html, /Work Plan.*Activity 1/);
    assert.match(html, /href="https:\/\/athena\.test\/papers\/9#activity-1"/);
    assert.match(html, /<h4[^>]*>Suggested next step<\/h4><p[^>]*>Add an activity for Objective 2\.<\/p>/);
    assert.ok(html.indexOf('Add an activity for Objective 2.') < html.indexOf('The saved activities cover Objective 1 only.'));
    assert.match(html, /<p class="assistant-review-explanation">The saved activities cover Objective 1 only\.<\/p>/);
    assert.match(html, /<details class="assistant-review-evidence"><summary>See supporting text \(1\)<\/summary>/);
    assert.doesNotMatch(html, /<details[^>]*\bopen\b|<h4[^>]*>Evidence<\/h4>/);
    assert.doesNotMatch(html, /Uploaded images were not read|reviewed_at|submitted_version/);
});

test('supporting text distinguishes reviewer requests, faculty responses, and proposal fields', () => {
    const html = assistantMarkdown.formatAssistantReview({ findings: [{
        title: 'Clarify participant selection', status: 'partly_addressed', next_edit: 'Describe how participants are selected.',
        explanation: 'The current response does not explain recruitment.',
        evidence: [
            { reference: 'Reviewer feedback', location: 'Reviewer comment 1', quote: 'Explain recruitment.', url: 'https://athena.test/reviews/1' },
            { reference: 'Reviewer feedback', location: 'Reviewer comment 1 · Faculty response', quote: 'We revised the design.', url: 'https://athena.test/reviews/1' },
            { reference: 'Detailed Proposal', location: 'Research design', quote: 'A descriptive design will be used.', url: 'https://athena.test/papers/1' },
        ],
    }] });

    assert.match(html, /<summary>See supporting text \(3\)<\/summary>/);
    assert.match(html, /Reviewer request.*Reviewer comment 1/);
    assert.match(html, /Your response.*Faculty response/);
    assert.match(html, /Proposal.*Detailed Proposal.*Research design/);
    assert.equal((html.match(/assistant-evidence-link/g) || []).length, 3);
});

test('reviews stay limited to five findings and use neutral faculty-facing statuses', () => {
    const html = assistantMarkdown.formatAssistantReview({ findings: [
        { title: 'Already covered', status: 'addressed', next_edit: 'No change is needed.' },
        { title: 'Compare the activity', status: 'observed_mismatch' },
        ...Array.from({ length: 4 }, (_, index) => ({ title: `Finding ${index + 1}`, status: 'needs_attention' })),
    ] });

    assert.equal((html.match(/<section class="assistant-review-finding">/g) || []).length, 5);
    assert.match(html, /data-review-status="addressed">Already addressed/);
    assert.match(html, /data-review-status="observed_mismatch">Needs attention/);
    assert.doesNotMatch(html, /Finding 4|Observed mismatch/);
});

test('structured review model fields cannot inject HTML, statuses, or executable URLs', () => {
    assert.equal(typeof assistantMarkdown.formatAssistantReview, 'function');
    const hostile = '<img src=x onerror="alert(1)">';
    const html = assistantMarkdown.formatAssistantReview({
        summary: hostile,
        findings: [{ title: hostile, status: '" onclick="alert(1)', explanation: hostile, next_edit: hostile,
            evidence: [{ reference: hostile, location: hostile, quote: hostile, url: 'javascript:alert(1)' }] }],
    });

    assert.doesNotMatch(html, /<img|onclick=|href="javascript:/);
    assert.match(html, /&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;/);
    assert.match(html, /Cannot verify/);
    assert.doesNotMatch(html, /assistant-evidence-link/);
});
