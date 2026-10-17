import test from 'node:test';
import assert from 'node:assert/strict';
import {
    allowedProposalChangeField,
    normalizeAssistantProposalChanges,
    proposalChangeHandoff,
    proposalChangeRichTextValue,
    researchAssistantProposalChanges,
    serializeAssistantProposalChanges,
} from '../../resources/js/research-assistant-proposal-changes.js';

const token = '9f7984c15ebc4bf2820d6d69f53306c99f7984c15ebc4bf2820d6d69f53306c9';

function candidate(overrides = {}) {
    return {
        id: 'change-1', token, paper_slug: 'detailed-proposal', field: 'rationale',
        label: 'Rationale', before: '  <p>Original wording.</p>\n',
        value: 'Describe the local problem and the evidence for it.', reason: 'Clarify the project need.',
        ...overrides,
    };
}

function packet(overrides = {}) {
    return {
        proposal_draft_id: 12, proposal_title: 'Community monitoring',
        editor_url: 'https://athena.test/faculty/proposal-drafts/12/detailed-proposal',
        assessment: { rrl: {
            count: 0, known_count: 0, count_status: 'empty', minimum: 10,
            shortfall: 10, minimum_satisfied: false, quality_verified: false, sources: [],
        } },
        suggestions: [candidate()], ...overrides,
    };
}

function storage() {
    const entries = new Map();
    return {
        getItem: (key) => entries.get(key) ?? null,
        setItem: (key, value) => entries.set(key, value),
        removeItem: (key) => entries.delete(key),
        entries,
    };
}

test('proposal candidates retain the exact original HTML without mutating the response', () => {
    const original = packet({ proposal_draft_id: '12' });
    const normalized = normalizeAssistantProposalChanges(original);
    assert.equal(normalized.proposal_draft_id, 12);
    assert.equal(normalized.suggestions[0].before, '  <p>Original wording.</p>\n');
    assert.equal(normalized.suggestions[0].value, 'Describe the local problem and the evidence for it.');
    normalized.suggestions[0].value = 'UI-local modification';
    assert.equal(original.suggestions[0].value, 'Describe the local problem and the evidence for it.');
});

test('malformed packets and unsafe draft identifiers cannot offer proposal changes', () => {
    for (const invalid of [null, [], new Date(), 'text', 12]) {
        assert.equal(normalizeAssistantProposalChanges(invalid), null);
    }
    for (const id of [0, -1, true, [], {}, 1.5, Number.MAX_SAFE_INTEGER + 1, '1e2', '12.0', ' 12 ']) {
        assert.equal(normalizeAssistantProposalChanges(packet({ proposal_draft_id: id })), null);
    }
});

test('only approved narrative fields and bounded objective indexes can be changed', () => {
    for (const field of ['executive_brief', 'rationale', 'general_objective', 'introduction',
        'methodology.research_design', 'methodology.data_analysis', 'related_literature',
        'specific_objectives.0.description', 'specific_objectives.49.description',
        'specific_method_objectives.0.heading', 'specific_method_objectives.19.methods.19.description',
        'expected_outputs.publication.0.description', 'expected_outputs.economic_impact.19.description',
        'responsibilities.29.duties']) {
        assert.equal(allowedProposalChangeField(field), true, field);
    }
    for (const field of ['references', 'methodology.specific_methods',
        'specific_objectives.50.description', 'specific_objectives.-1.description',
        'specific_objectives.01.description', 'specific_objectives.1.description.extra',
        'specific_method_objectives.20.heading', 'specific_method_objectives.0.methods.20.description',
        'expected_outputs.made_up.0.description', 'expected_outputs.policy.20.description',
        'expected_outputs.policy.0.quantity', 'responsibilities.30.duties', 'responsibilities.0.name',
        '__proto__', 'constructor.prototype', ' rationale', {}, null]) {
        assert.equal(allowedProposalChangeField(field), false);
        assert.deepEqual(normalizeAssistantProposalChanges(packet({ suggestions: [candidate({ field })] })).suggestions, []);
    }
});

test('each paper accepts only its existing narrative field contract and size limits', () => {
    for (const [paper_slug, field, maximum] of [
        ['work-plan', 'entries.19.expected_output', 500], ['work-plan', 'entries.0.activity', 1500],
        ['expense-breakdown', 'items.99.details', 500], ['expense-breakdown', 'items.0.purpose', 500],
    ]) {
        assert.equal(allowedProposalChangeField(field, paper_slug), true);
        const suggestion = candidate({ paper_slug, field, value: 'x'.repeat(maximum) });
        assert.equal(normalizeAssistantProposalChanges(packet({ suggestions: [suggestion] })).suggestions[0].paper_slug, paper_slug);
        suggestion.value += 'x';
        assert.deepEqual(normalizeAssistantProposalChanges(packet({ suggestions: [suggestion] })).suggestions, []);
    }
    for (const [paper_slug, field] of [
        ['work-plan', 'entries.20.activity'], ['work-plan', 'entries.0.objective'], ['work-plan', 'entries.0.months'],
        ['expense-breakdown', 'items.100.details'], ['expense-breakdown', 'items.0.amount'],
        ['expense-breakdown', 'items.0.particulars'], ['work-plan', 'rationale'], ['detailed-proposal', 'items.0.details'],
    ]) {
        assert.equal(allowedProposalChangeField(field, paper_slug), false);
    }
});

test('paper handoffs retain only the supported destination slug with the selection', () => {
    const handoff = proposalChangeHandoff(storage(), 7, () => 1000);
    assert.equal(handoff.stage({ proposal_draft_id: 12, token, suggestion_id: 'change-1', paper_slug: 'work-plan' }), true);
    assert.equal(handoff.read(12).paper_slug, 'work-plan');
});

test('whole RRL previews are bounded separately from short narrative suggestions', () => {
    for (const property of ['before', 'value']) {
        const suggestions = [candidate({ field: 'related_literature', [property]: 'x'.repeat(30000) })];
        assert.equal(normalizeAssistantProposalChanges(packet({ suggestions })).suggestions[0][property].length, 30000);
        suggestions[0][property] += 'x';
        assert.deepEqual(normalizeAssistantProposalChanges(packet({ suggestions })).suggestions, []);
    }
});

test('suggestions require plain data, supported papers, safe tokens and typed identifiers', () => {
    for (const invalid of [null, [], new Date(), candidate({ id: '' }), candidate({ id: {} }),
        candidate({ id: -1 }), candidate({ id: 'x'.repeat(81) }), candidate({ token: '' }), candidate({ token: 'short' }),
        candidate({ token: 'x'.repeat(65) }), candidate({ token: 'x'.repeat(63) }), candidate({ token: 'javascript:bad-token' }),
        candidate({ paper_slug: 'line-item-budget' }), candidate({ before: {} }),
        candidate({ value: null }), candidate({ value: '   ' })]) {
        assert.deepEqual(normalizeAssistantProposalChanges(packet({ suggestions: [invalid] })).suggestions, []);
    }
    assert.equal(normalizeAssistantProposalChanges(packet({ suggestions: [candidate({ id: 7 })] })).suggestions[0].id, 7);
});

test('oversized replacements are refused while display text and candidate counts stay bounded', () => {
    for (const property of ['before', 'value']) {
        assert.deepEqual(normalizeAssistantProposalChanges(packet({ suggestions: [candidate({ [property]: 'x'.repeat(6001) })] })).suggestions, []);
        assert.equal(normalizeAssistantProposalChanges(packet({ suggestions: [candidate({ [property]: 'x'.repeat(6000) })] })).suggestions[0][property].length, 6000);
    }
    const normalized = normalizeAssistantProposalChanges(packet({
        proposal_title: 't'.repeat(400),
        suggestions: Array.from({ length: 8 }, (_, index) => candidate({
            id: `change-${index}`, token: `${token.slice(0, 63)}${index}`, label: 'l'.repeat(200), reason: 'r'.repeat(3000),
        })),
    }));
    assert.equal(normalized.proposal_title.length, 300);
    assert.equal(normalized.suggestions.length, 3);
    assert.equal(normalized.suggestions[0].label.length, 160);
    assert.equal(normalized.suggestions[0].reason.length, 1000);
});

test('duplicate suggestion identifiers or tokens cannot offer the same candidate twice', () => {
    const normalized = normalizeAssistantProposalChanges(packet({ suggestions: [
        candidate(), candidate({ token: `${token.slice(0, 63)}a` }), candidate({ id: 'change-2' }),
    ] }));
    assert.equal(normalized.suggestions.length, 1);
});

test('editor links require bounded credential-free HTTP URLs', () => {
    for (const url of ['javascript:alert(1)', 'data:text/html,test', '/relative',
        'https://user:secret@athena.test/editor', 'https://athena.test/with space',
        `https://athena.test/${'x'.repeat(2048)}`, {}, null]) {
        assert.equal(normalizeAssistantProposalChanges(packet({ editor_url: url })).editor_url, '');
    }
    assert.equal(normalizeAssistantProposalChanges(packet()).editor_url,
        'https://athena.test/faculty/proposal-drafts/12/detailed-proposal');
});

test('unknown and partial RRL counts cannot claim a verified minimum or a shortfall', () => {
    for (const count_status of ['unknown', 'partial']) {
        const rrl = normalizeAssistantProposalChanges(packet({ assessment: { rrl: {
            count_status, count: 3, known_count: 3, minimum: 10, shortfall: 7,
            minimum_satisfied: true, quality_verified: true, sources: [],
        } } })).assessment.rrl;
        assert.equal(rrl.count, null);
        assert.equal(rrl.known_count, 3);
        assert.equal(rrl.shortfall, null);
        assert.equal(rrl.minimum_satisfied, null);
        assert.equal(rrl.quality_verified, false);
    }
    const unknown = normalizeAssistantProposalChanges(packet({ assessment: { rrl: { count_status: 'unknown' } } })).assessment.rrl;
    assert.equal(unknown.known_count, null);
    assert.equal(unknown.count, null);
    assert.equal(unknown.shortfall, null);
});

test('assessment retains safe manual metadata without retaining source content', () => {
    const rrl = normalizeAssistantProposalChanges(packet({ assessment: { rrl: {
        count_status: 'tracked', count: 12, known_count: 12, minimum: 10,
        shortfall: 0, minimum_satisfied: true, quality_verified: false,
        sources: [{ title: 'Research Manual', reference: 'Manual 1', type: 'Official Research Manual',
            category: 'Institutional policy', section: '3.5 Evaluation', page_start: 34,
            page_end: 35, source_kind: 'policy', document_version: 'a'.repeat(64), url: null, content: 'Private full source text',
            malicious: { nested: true } }],
    } } })).assessment.rrl;
    assert.equal(rrl.count, 12);
    assert.equal(rrl.shortfall, 0);
    assert.equal(rrl.minimum_satisfied, true);
    assert.equal(rrl.sources[0].title, 'Research Manual');
    assert.equal(rrl.sources[0].page_end, 35);
    assert.equal(rrl.sources[0].document_version, 'a'.repeat(64));
    assert.equal('content' in rrl.sources[0], false);
    assert.equal('malicious' in rrl.sources[0], false);
});

test('source document versions stay bounded in history metadata', () => {
    const rrl = serializeAssistantProposalChanges(packet({ assessment: { rrl: {
        count_status: 'unknown', sources: [{ document_version: 'b'.repeat(100) }, { document_version: {} }],
    } } })).assessment.rrl;
    assert.equal(rrl.sources[0].document_version, 'b'.repeat(64));
    assert.equal(rrl.sources[1].document_version, '');
});

test('requirement references survive normalization and history without private source text', () => {
    const original = packet({ assessment: { rrl: {
        count_status: 'unknown', requirement_reference: 'R'.repeat(100),
        sources: [{ reference: 'Manual requirement 2', content: 'PRIVATE_MANUAL_SOURCE_CONTENT' }],
    } } });
    for (const normalize of [normalizeAssistantProposalChanges, serializeAssistantProposalChanges]) {
        const normalized = normalize(original);
        assert.equal(normalized.assessment.rrl.requirement_reference, 'R'.repeat(80));
        assert.equal(normalized.assessment.rrl.sources[0].reference, 'Manual requirement 2');
        assert.equal(JSON.stringify(normalized).includes('PRIVATE_MANUAL_SOURCE_CONTENT'), false);
    }
});

test('history serialization strips transient UI state and unknown properties', () => {
    const original = packet({ is_working: true, error: 'Temporary error', applied: true });
    original.suggestions[0].notice = 'Saved';
    original.suggestions[0].is_working = true;
    const persisted = serializeAssistantProposalChanges(original);
    for (const property of ['is_working', 'error', 'applied']) assert.equal(property in persisted, false);
    assert.equal('notice' in persisted.suggestions[0], false);
    assert.equal('is_working' in persisted.suggestions[0], false);
    assert.equal(persisted.suggestions[0].before, '  <p>Original wording.</p>\n');
    assert.deepEqual(researchAssistantProposalChanges(), { proposalChangesOperationPending: false });
});

test('plain suggested wording becomes escaped paragraphs and keeps single line breaks', () => {
    assert.equal(proposalChangeRichTextValue('First <script>alert("x")</script> & claim.\r\nNext line.\r\n\r\nSecond \'paragraph\'.'),
        '<p>First &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; claim.<br>Next line.</p><p>Second &#039;paragraph&#039;.</p>');
    assert.equal(proposalChangeRichTextValue('   '), '');
    assert.equal(proposalChangeRichTextValue({ html: '<img src=x>' }), '');
});

test('handoffs contain only a token and identifier and stay account/proposal scoped', () => {
    const store = storage();
    const handoff = proposalChangeHandoff(store, 7, () => 1000);
    assert.equal(handoff.stage({ proposal_draft_id: 12, token, suggestion_id: 'change-1',
        value: 'Private proposed text', before: '<p>Private original</p>', editor_url: 'https://other.test' }), true);
    const stored = JSON.parse([...store.entries.values()][0]);
    assert.deepEqual(stored, { proposal_draft_id: 12, token, suggestion_id: 'change-1', created_at: 1000 });
    assert.deepEqual(handoff.read(12), stored);
    assert.equal(handoff.read(13), null);
    assert.equal(proposalChangeHandoff(store, 8, () => 1000).read(12), null);
    handoff.clear(12);
    assert.equal(handoff.read(12), null);
});

test('expired future dated and malformed handoffs are removed instead of applied', () => {
    const store = storage();
    const first = proposalChangeHandoff(store, 7, () => 1000);
    first.stage({ proposal_draft_id: 12, token, suggestion_id: 1 });
    assert.equal(proposalChangeHandoff(store, 7, () => 601000).read(12), null);
    assert.equal(store.entries.size, 0);
    first.stage({ proposal_draft_id: 12, token, suggestion_id: 1 });
    assert.equal(proposalChangeHandoff(store, 7, () => 999).read(12), null);
    first.stage({ proposal_draft_id: 12, token, suggestion_id: 1 });
    const key = [...store.entries.keys()][0];
    for (const malformed of ['{bad', '[]', JSON.stringify({ proposal_draft_id: 13, token, suggestion_id: 1, created_at: 1000 }),
        JSON.stringify({ proposal_draft_id: 12, token: 'short', suggestion_id: 1, created_at: 1000 }),
        JSON.stringify({ proposal_draft_id: 12, token, suggestion_id: {}, created_at: 1000 })]) {
        store.setItem(key, malformed);
        assert.equal(first.read(12), null);
        assert.equal(store.entries.size, 0);
    }
});

test('invalid handoff identifiers and blocked browser storage fail without throwing', () => {
    const store = storage();
    for (const account of [0, -1, true, {}, 2.5]) {
        const handoff = proposalChangeHandoff(store, account);
        assert.equal(handoff.stage({ proposal_draft_id: 12, token, suggestion_id: 1 }), false);
        assert.equal(handoff.read(12), null);
    }
    const valid = proposalChangeHandoff(store, 7);
    for (const invalid of [{ proposal_draft_id: true, token, suggestion_id: 1 },
        { proposal_draft_id: 12, token: '', suggestion_id: 1 },
        { proposal_draft_id: 12, token, suggestion_id: [] }]) {
        assert.equal(valid.stage(invalid), false);
    }
    for (const unavailable of [null, { getItem() { throw Error('Blocked'); },
        setItem() { throw Error('Blocked'); }, removeItem() { throw Error('Blocked'); } }]) {
        const handoff = proposalChangeHandoff(unavailable, 7);
        assert.equal(handoff.stage({ proposal_draft_id: 12, token, suggestion_id: 1 }), false);
        assert.equal(handoff.read(12), null);
        assert.doesNotThrow(() => handoff.clear(12));
    }
});
