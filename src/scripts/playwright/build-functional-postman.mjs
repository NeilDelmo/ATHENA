import { mkdirSync, writeFileSync } from 'node:fs';

export function functionalCollection() {
    const item = [];
    const check = (label, expression) => `pm.test(${JSON.stringify(label)}, function () { ${expression} });`;
    function add(name, method, path, status, extra = {}, checks = [], setup = false) {
        const headers = [ { key: 'Accept', value: 'application/json' }, { key: 'X-Playwright-Key', value: '{{fixtureKey}}' } ];
        if (!['GET', 'HEAD'].includes(method)) headers.push({ key: 'X-CSRF-TOKEN', value: '{{csrf}}' });
        const request = { method, header: headers, url: `{{baseURL}}/${path}`, ...extra };
        if (extra.json !== undefined) {
            delete request.json;
            headers.push({ key: 'Content-Type', value: 'application/json' });
            request.body = { mode: 'raw', raw: JSON.stringify(extra.json) };
        }
        item.push({ name, request, protocolProfileBehavior: { followRedirects: false }, event: [{ listen: 'test', script: { type: 'text/javascript', exec: [
            check(`${setup ? 'SETUP' : name.split(' ')[0]}: ${name} response`, `pm.expect(pm.response.code).to.eql(${status});`), ...checks,
        ] } }] });
    }
    function auth(actor, workspace) {
        const sessionActor = actor === 'faculty' ? 'functional_owner' : actor;
        add(`SETUP session ${actor}`, 'GET', `__playwright/session/${sessionActor}${workspace ? '?workspace='+workspace : ''}`, 204, {}, [], true);
        add(`SETUP csrf ${actor}`, 'GET', 'dashboard', 200, {}, [], true);
        const route = { head: 'research-head/dashboard', faculty: 'faculty/dashboard', outsider: 'faculty/dashboard', secretary: 'research-secretary/dashboard', coordinator: 'research-coordinator/dashboard' }[actor];
        add(`SETUP identity ${actor}`, 'GET', route, 200, { header: [{ key: 'Accept', value: 'text/html' }] }, [
            `const match = pm.response.text().match(/name="csrf-token"\\s+content="([^"]+)"/); if (match) pm.collectionVariables.set('csrf', match[1]);`,
            check('SETUP authenticated identity', `pm.expect(pm.response.text()).to.include('data-auth-user-id="' + pm.environment.get('${actor}Id') + '"'); pm.expect(match).not.to.eql(null);`),
        ], true);
    }
    function state(name, checks = []) {
        add(name, 'GET', '__playwright/functional-state', 200, {}, [
            `const state = pm.response.json(); pm.collectionVariables.set('budgetToken', state.budget.token);`, ...checks,
        ]);
    }
    const error = field => check(`Validation identifies ${field}`, `pm.expect(pm.response.json().errors).to.have.property(${JSON.stringify(field)});`);
    const budgetRows = values => ['Purchase Request', 'Cash Advance', 'Request of Payment'].map((type, index) => ({ type, amount_requested: 30000, actual_amount: values[index] }));
    const budgetURL = 'projects/{{projectId}}/reports/{{reportId}}/budget';
    const originalRows = budgetRows([10000, 8500, 2500]);
    const unchangedBudget = check('Budget and report associations unchanged', `pm.expect(state.budget.spent).to.eql(21000); pm.expect(state.budget.remaining).to.eql(79000); pm.expect(state.otherAllocation).to.eql(100000); pm.expect(state.report.topic_id).to.eql(Number(pm.environment.get('projectId'))); pm.expect(state.report.submitted_by).to.eql(Number(pm.environment.get('facultyId')));`);

    // Every mutation in this collection is guarded to the dedicated local fixture server.
    const call = { title: 'Postman functional call', academic_year: '2026-2027', opens_at: '2026-01-01', closes_at: '2027-01-01', max_active_research_per_faculty: 2, status: 'draft', description: 'Reference description' };
    auth('head');
    add('CALL-02 [missing title]', 'POST', 'research-calls', 422, { json: { ...call, title: '' } }, [error('title')]);
    add('CALL-02 [end before start]', 'POST', 'research-calls', 422, { json: { ...call, closes_at: '2025-01-01' } }, [error('closes_at')]);
    state('CALL-02 [invalid calls not stored]', [check('No invalid call created', `pm.expect(state.calls.filter(c => c.title === 'Postman functional call')).to.have.lengthOf(0);`)]);
    add('CALL-01 [create draft]', 'POST', 'research-calls', 302, { json: call });
    state('CALL-01 [stored fields]', [
        `const call = state.calls.find(c => c.title === 'Postman functional call'); if (call) pm.collectionVariables.set('createdCallId', call.id);`,
        check('Created call values match', `pm.expect(call).to.include({title:'Postman functional call', description:'Reference description', status:'draft', academic_year:'2026-2027'});`),
    ]);
    add('CALL-01 [publish]', 'PATCH', 'research-calls/{{createdCallId}}/status', 302, { json: { status: 'open' } });
    state('CALL-01 [published]', [check('Publication persisted', `pm.expect(state.calls.find(c => c.id === Number(pm.collectionVariables.get('createdCallId'))).status).to.eql('open');`)]);
    add('CALL-04 [edit]', 'PUT', 'research-calls/{{createdCallId}}', 302, { json: { ...call, status: 'open', description: 'Updated reference description' } });
    state('CALL-04 [persisted edit]', [check('Only intended call fields changed', `pm.expect(state.calls.find(c => c.id === Number(pm.collectionVariables.get('createdCallId')))).to.include({title:'Postman functional call',description:'Updated reference description',status:'open',academic_year:'2026-2027'});`)]);
    add('EXTRACT-03 [unsupported reference input]', 'POST', 'research-calls/extract-image', 422, { body: { mode: 'formdata', formdata: [{ key: 'reference_image', type: 'file', src: 'test-results/lifecycle-fixtures/unsupported.txt' }] } }, [error('reference_image')]);
    add('APPR-04 [repeat finalized approval]', 'PATCH', 'research-head/topics/{{projectId}}/status', 422, { json: { status: 'ready_for_signature', lrec_clearance_confirmed: true } });
    state('APPR-04 [no duplicate transition]', [check('Approved project preserved', `pm.expect(state.project.status).to.eql('approved'); pm.expect(state.project.project_status).to.eql('ongoing'); pm.expect(state.projectTransitions).to.eql(1); pm.expect(state.projectVersions).to.eql(0);`)]);

    auth('faculty');
    add('CALL-03 [published visible, draft hidden]', 'GET', 'research-calls', 200, {}, [
        check('Published call visible', `pm.expect(pm.response.text()).to.include('Postman functional call');`),
        check('Unpublished call hidden', `pm.expect(pm.response.text()).not.to.include('Functional unpublished call');`),
    ]);
    add('ACCESS-02 [forbidden call mutation]', 'POST', 'research-calls', 403, { json: call });
    add('APPR-02 [faculty cannot approve]', 'PATCH', 'research-head/topics/{{projectId}}/status', 403, { json: { status: 'ready_for_signature', lrec_clearance_confirmed: true } });
    add('PROP-04 [closed/unpublished call prerequisite]', 'POST', 'faculty/proposal-drafts/{{draftId}}/submit', 422, { json: {} });
    auth('faculty', 'faculty_researcher');
    add('PROJ-04 [incomplete monitoring data]', 'POST', 'research/{{projectId}}/progress-reports/prepare', 422, { json: {} });
    for (const [variation, messages] of [['empty', []], ['whitespace', [{ role: 'user', content: '   ' }]]]) {
        add(`HELP-02 [${variation}]`, 'POST', 'research-support/chat', 422, { json: { messages } }, [check('Required message content rejected', `pm.expect(Object.keys(pm.response.json().errors).some(k => k.startsWith('messages'))).to.eql(true);`)]);
    }
    const fileURL = 'topics/{{projectId}}/documents/{{documentId}}/download';
    add('DOC-01 [known file bytes]', 'GET', fileURL, 200, {}, [
        check('Returned file matches independent SHA256', `pm.expect(require('crypto-js').SHA256(pm.response.text()).toString()).to.eql(pm.environment.get('documentChecksum')); pm.expect(pm.response.text()).to.include('Expected content: coastal baseline 2026');`),
    ]);
    const upload = (src, filename) => ({ body: { mode: 'formdata', formdata: [
        { key: 'category', value: 'supporting_files', type: 'text' },
        { key: 'documents[]', src, type: 'file', ...(filename ? { fileName: filename } : {}) },
    ] } });
    add('DOC-02 [unsupported file]', 'POST', 'topics/{{projectId}}/documents', 422, upload('test-results/lifecycle-fixtures/unsupported.txt'), [error('documents.0')]);
    add('DOC-03 [configured 25000 KiB boundary]', 'POST', 'topics/{{projectId}}/documents', 302, upload('test-results/functional-fixtures/boundary.pdf'));
    state('DOC-03 [boundary saved]', [`pm.collectionVariables.set('documentCount',state.documents.length);`, check('Boundary bytes stored', `pm.expect(state.documents.some(d => d.file_size === 25600000)).to.eql(true);`)]);
    add('DOC-03 [one byte over boundary]', 'POST', 'topics/{{projectId}}/documents', 422, upload('test-results/functional-fixtures/oversized.pdf'), [error('documents.0')]);
    state('DOC-02 DOC-03 [invalid uploads not stored]', [check('No invalid document was added', `pm.expect(state.documents.length).to.eql(Number(pm.collectionVariables.get('documentCount')));`)]);
    add('DOC-01 [upload reference]', 'POST', 'topics/{{projectId}}/documents', 302, upload('test-results/lifecycle-fixtures/reference.pdf'));
    state('DOC-01 [upload association]', [
        `const doc = state.documents.filter(d => d.original_filename === 'reference.pdf').at(-1); if(doc) pm.collectionVariables.set('uploadedDocumentId',doc.id);`,
        check('Uploaded PDF bound to intended record', `pm.expect(doc.topic_id).to.eql(Number(pm.environment.get('projectId'))); pm.expect(doc.checksum).to.eql(pm.environment.get('documentChecksum'));`),
    ]);
    add('DOC-01 [retrieve uploaded reference]', 'GET', 'topics/{{projectId}}/documents/{{uploadedDocumentId}}/download', 200, {}, [check('Upload roundtrip SHA256', `pm.expect(require('crypto-js').SHA256(pm.response.text()).toString()).to.eql(pm.environment.get('documentChecksum'));`)]);
    add('DOC-05 [missing file reference]', 'GET', 'topics/{{projectId}}/documents/{{missingDocumentId}}/download', 404, {}, [check('No unrelated PDF leaked', `pm.expect(pm.response.text()).not.to.include('Expected content: coastal baseline 2026');`)]);
    add('MAN-01 [active edition bytes]', 'GET', 'research-manuals/{{activeManualVersion}}/file', 200, {}, [check('Correct edition PDF retrieved', `pm.expect(require('crypto-js').SHA256(pm.response.text()).toString()).to.eql(pm.environment.get('documentChecksum'));`)]);
    add('MAN-02 [staged edition restricted]', 'GET', 'research-manuals/{{stagedManualVersion}}/file', 403);
    add('MAN-04 [unknown edition]', 'GET', `research-manuals/${'0'.repeat(64)}/file`, 404);

    auth('outsider');
    add('ACCESS-03 PROP-05 [restricted draft read]', 'GET', 'faculty/proposal-drafts/{{draftId}}/details', 403);
    add('ACCESS-03 PROP-05 [restricted draft update]', 'PUT', 'faculty/proposal-drafts/{{draftId}}/details', 403, { json: { project_title: 'Unauthorized overwrite' } });
    auth('outsider', 'faculty_researcher');
    add('ACCESS-03 [own project permitted]', 'GET', 'research/{{otherProjectId}}', 200, {}, [check('Own project is readable', `pm.expect(pm.response.text()).to.include('Functional other project');`)]);
    add('PROJ-03 [restricted project read]', 'GET', 'research/{{projectId}}', 403);
    add('PROJ-03 [restricted project update]', 'POST', 'research/{{projectId}}/progress-reports/prepare', 403, { json: {} });
    add('DOC-04 [restricted direct retrieval]', 'GET', fileURL, 403);
    add('DOC-04 [wrong project document pairing]', 'GET', 'topics/{{otherProjectId}}/documents/{{documentId}}/download', 404);
    add('BUD-05 [restricted financial write]', 'PUT', budgetURL, 403, { json: { budget_token: '{{budgetToken}}', budget_utilization: budgetRows([1, 2, 3]) } });
    state('ACCESS-02 ACCESS-03 PROP-05 PROJ-03 BUD-05 [unchanged protected state]', [
        unchangedBudget,
        check('Protected draft and project unchanged', `pm.expect(state.draft.project_title).to.eql('Functional incomplete draft'); pm.expect(state.draft.user_id).to.eql(Number(pm.environment.get('facultyId'))); pm.expect(state.project.status).to.eql('approved');`),
        check('Unauthorized call creation produced no duplicate', `pm.expect(state.calls.filter(c => c.title === 'Postman functional call')).to.have.lengthOf(1);`),
    ]);

    auth('faculty', 'faculty_researcher');
    state('BUD-01 BUD-02 [persisted reference totals]', [unchangedBudget]);
    for (const [variation, value] of [['negative', -1], ['nonnumeric', 'invalid']]) {
        const rows = structuredClone(originalRows); rows[0].actual_amount = value;
        add(`BUD-03 [${variation}]`, 'PUT', budgetURL, 422, { json: { budget_token: '{{budgetToken}}', budget_utilization: rows } }, [error('budget_utilization.0.actual_amount')]);
    }
    const overBudget = budgetRows([40000, 40000, 40000]).map(r => ({ ...r, amount_requested: 40000 }));
    add('BUD-04 [allocation exceeded]', 'PUT', budgetURL, 422, { json: { budget_token: '{{budgetToken}}', budget_utilization: overBudget } }, [error('budget_utilization')]);
    state('BUD-03 BUD-04 [rejected amounts do not change totals]', [unchangedBudget]);

    auth('secretary');
    add('BUD-05 [office secretary cannot edit project budget]', 'PUT', budgetURL, 403, { json: { budget_token: '{{budgetToken}}', budget_utilization: originalRows } });
    add('AUTH-05 [secretary logout]', 'POST', 'logout', 302, { json: {} });
    for (const [variation, path] of [['dashboard', 'research-secretary/dashboard'], ['document', fileURL], ['manual', 'research-manuals/{{activeManualVersion}}/file']]) {
        add(`ACCESS-04 DOC-04 MAN-02 [signed out ${variation}]`, 'GET', path, 401, {}, [check('No private response data', `pm.expect(pm.response.text()).not.to.include('Expected content: coastal baseline 2026');`)]);
    }
    return {
        info: { name: 'ATHENA functional correctness — isolated local endpoints', schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            description: 'Workbook case IDs label requests and assertions. Synthetic local session setup is not a Google login test. State checks use a key-protected loopback-only fixture oracle. Imported collections require the generated private local environment. No deployed mutation is supported.' },
        event: [{ listen: 'prerequest', script: { type: 'text/javascript', exec: [
            `const target = pm.environment.get('baseURL'); if (!new RegExp('^http://(127[.]0[.]0[.]1|localhost):[0-9]+$').test(target || '') || !pm.environment.get('fixtureKey')) { pm.execution.skipRequest(); throw new Error('Refusing nonlocal functional fixture execution.'); }`,
        ] } }],
        item,
    };
}

export function writeFunctionalCollection() {
    mkdirSync('scripts/postman', { recursive: true });
    const collection = functionalCollection();
    writeFileSync('scripts/postman/ATHENA.functional.postman_collection.json', `${JSON.stringify(collection, null, 2)}\n`);
    return collection;
}
