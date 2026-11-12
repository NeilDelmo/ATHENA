export const baseUrl = 'https://athenaresearch.app';
export const workspaces = {
    research_head: ['/research-head/dashboard', 'data-research-head-navigation'],
    faculty: ['/faculty/dashboard', 'data-faculty-navigation'],
    faculty_researcher: ['/faculty/dashboard', 'data-faculty-navigation'],
    research_office: ['/research-coordinator/dashboard', 'data-research-office-navigation'],
    research_secretary: ['/research-secretary/dashboard', 'data-research-secretary-navigation'],
    vcrdes: ['/vcrdes/dashboard', 'data-vcrdes-navigation'],
};

const operations = [];
function add(id, module, name, roles, path, extra = {}) {
    operations.push({ id, module, name, roles: roles.split(','), path, method: 'GET', status: [200], ...extra });
}
add('head-dashboard', '01 Dashboard and analytics', 'Head dashboard HTML', 'research_head', '/research-head/dashboard');
add('head-analytics', '01 Dashboard and analytics', 'Analytics HTML and initial component', 'research_head', '/research-head/analytics');
add('head-analytics-refresh', '01 Dashboard and analytics', 'Analytics Livewire refresh', 'research_head', '/livewire/update', { method: 'POST', component: 'research-head-dashboard', depends: 'head-analytics' });
add('faculty-dashboard', '01 Dashboard and analytics', 'Faculty dashboard HTML', 'faculty,faculty_researcher', '/faculty/dashboard');
add('coordinator-dashboard', '01 Dashboard and analytics', 'Coordinator dashboard HTML', 'research_office', '/research-coordinator/dashboard');
add('secretary-dashboard', '01 Dashboard and analytics', 'Secretary dashboard HTML', 'research_secretary', '/research-secretary/dashboard');
add('calls', '02 Research calls', 'Research call list', 'research_head,faculty,faculty_researcher', '/research-calls');
add('call-details', '02 Research calls', 'Research call detail', 'research_head,faculty,faculty_researcher', '/research-calls?call=${call_id}', { records: ['call_id'] });
add('create-call', '02 Research calls', 'Create a draft research call', 'research_head', '/research-calls', { method: 'POST', write: true, status: [302,303], location: '/research-calls', payload: true });
add('proposal-drafts', '03 Proposals and approval', 'Proposal draft list', 'faculty', '/faculty/proposal-drafts');
add('proposal-detail', '03 Proposals and approval', 'Proposal draft workspace', 'faculty', '/faculty/proposal-drafts/${draft_id}', { records: ['draft_id'] });
add('submitted-proposals', '03 Proposals and approval', 'Submitted proposal list', 'faculty', '/faculty/submissions');
add('head-submissions', '03 Proposals and approval', 'Proposal review list', 'research_head', '/research-head/proposal-submissions');
add('coordinator-proposals', '03 Proposals and approval', 'College proposal list', 'research_office', '/research-coordinator/proposals');
add('review-proposal', '03 Proposals and approval', 'Proposal review details', 'research_head,research_office', '/topics/${topic_id}', { records: ['topic_id'] });
add('create-draft', '03 Proposals and approval', 'Create a proposal draft', 'faculty', '/faculty/proposal-drafts', { method: 'POST', write: true, status: [302,303], location: '/faculty/proposal-drafts/[0-9]+', payload: true });
add('submit-proposal', '03 Proposals and approval', 'Submit an already complete proposal', 'faculty', '/faculty/proposal-drafts/${draft_id}/submit', { method: 'POST', write: true, records: ['draft_id'], payload: true, status: [302,303], location:'/faculty/dashboard', note: 'Requires a complete, eligible proposal package belonging to this test account. One submission per fixture.' });
add('head-projects', '04 Project monitoring', 'Ongoing project list', 'research_head', '/research-head/projects');
add('head-completed', '04 Project monitoring', 'Completed project list', 'research_head', '/research-head/completed-projects');
add('report-reviews', '04 Project monitoring', 'Head report review list', 'research_head', '/research-head/report-reviews');
add('researcher-projects', '04 Project monitoring', 'Researcher project list', 'faculty_researcher', '/research');
add('researcher-project', '04 Project monitoring', 'Researcher project details', 'faculty_researcher', '/research/${topic_id}', { records: ['topic_id'] });
add('secretary-reports', '04 Project monitoring', 'Secretary report review list', 'research_secretary', '/research-secretary/reports');
add('submit-monitoring', '04 Project monitoring', 'Submit a prepared monitoring report', 'faculty_researcher', '/research/${topic_id}/progress-reports/${report_id}/submit', { method: 'POST', write: true, records: ['topic_id','report_id'], payload: true, status: [302,303], location:'/topics/[0-9]+', note: 'A separately prepared, budget-complete, eligible report is required per user.' });
add('head-budgets', '05 Budget monitoring', 'Head budget list', 'research_head', '/research-head/budgets');
add('secretary-budgets', '05 Budget monitoring', 'Secretary budget list', 'research_secretary', '/research-secretary/budgets');
add('budget-form', '05 Budget monitoring', 'Prepared report budget form', 'research_secretary', '/research-secretary/projects/${topic_id}/reports/${report_id}/budget', { records: ['topic_id','report_id'] });
add('save-budget', '05 Budget monitoring', 'Save prepared report budget', 'research_secretary', '/research-secretary/projects/${topic_id}/reports/${report_id}/budget', { method: 'PUT', write: true, records: ['topic_id','report_id'], payload: true, status: [302,303], location:'/research-secretary/dashboard', depends: 'budget-form', budgetToken: true });
for (const [role, prefix] of [['research_head','research-head/research-targets'],['research_secretary','research-secretary/targets'],['research_office','research-coordinator/targets']]) {
    add(`targets-${role}`, '06 Research targets', 'Target board HTML and initial component', role, `/${prefix}`);
    add(`targets-refresh-${role}`, '06 Research targets', 'Target board Livewire refresh', role, '/livewire/update', { method:'POST', component:'research-target-board', depends:`targets-${role}` });
}
add('college-reports-head', '07 Accomplishment reports', 'College report list', 'research_head', '/research-head/quarterly-accomplishment-reports');
add('rms-reports', '07 Accomplishment reports', 'RMS quarterly report list', 'research_head', '/research-head/rms-quarterly-reports');
add('college-reports', '07 Accomplishment reports', 'Coordinator report workspace', 'research_office', '/research-coordinator/reports');
add('college-report-download', '07 Accomplishment reports', 'Download college report', 'research_office', '/research-coordinator/reports/download', { binary: true, optional:true, note:'Configure report period query parameters in the operation override if required.' });
add('notice-download', '08 Documents and generation', 'Download issued Notice to Proceed', 'research_head,faculty,faculty_researcher', '/topics/${topic_id}/notice-to-proceed', { records:['topic_id'], binary:true });
add('project-document', '08 Documents and generation', 'Download project document', 'research_head,faculty_researcher', '/topics/${topic_id}/documents/${document_id}/download', { records:['topic_id','document_id'], binary:true });
add('manuals', '08 Documents and generation', 'Research manual administration list', 'research_head', '/research-head/research-manuals');
add('manual-file', '08 Documents and generation', 'Retrieve research manual file', 'research_head,faculty,faculty_researcher,research_office,research_secretary', '/research-manuals/${manual_version}/file', { records:['manual_version'], binary:true });
add('research-support', '09 Research help', 'Research help HTML', 'faculty,faculty_researcher', '/research-support');
add('saved-literature', '09 Research help', 'Saved literature records', 'faculty,faculty_researcher', '/research-support/literature-library', { json:true });
add('assistant-history', '09 Research help', 'Assistant conversation list', 'faculty,faculty_researcher', '/research-support/history', { json:true });
add('assistant-chat', '09 Research help', 'Live research assistant request', 'faculty,faculty_researcher', '/research-support/chat', { method:'POST', external:true, payload:true, json:true, note:'Live third-party processing and configured per-user rate limits are included.' });
add('literature-search', '09 Research help', 'Live literature search', 'faculty,faculty_researcher', '/research-support/literature-search', { method:'POST', external:true, payload:true, json:true });
add('literature-synthesis', '09 Research help', 'Live literature synthesis', 'faculty,faculty_researcher', '/research-support/literature-synthesis', { method:'POST', external:true, payload:true, json:true, note:'Supply supported source tokens from test material, not fabricated tokens.' });
add('journal-search', '09 Research help', 'Live journal search', 'faculty_researcher', '/research-support/journal-search', { method:'POST', external:true, payload:true, json:true });
add('conference-search', '09 Research help', 'Live conference search', 'faculty_researcher', '/research-support/conference-search', { method:'POST', external:true, payload:true, json:true });
add('outcomes', '10 Post-implementation', 'Research outcome list', 'research_head,research_office,faculty_researcher', '/research-outcomes');
add('project-outcomes', '10 Post-implementation', 'Completed project outcomes', 'research_head,research_office,faculty_researcher', '/research-outcomes/projects/${topic_id}', { records:['topic_id'] });
add('create-outcome', '10 Post-implementation', 'Register a non-school-funded research outcome', 'faculty_researcher', '/research-outcomes', { method:'POST', write:true, payload:true, status:[302,303], location:'/research-outcomes/records/[0-9]+' });

add('vcrdes-dashboard', '01 Dashboard and analytics', 'VCRDES oversight dashboard', 'vcrdes', '/vcrdes/dashboard');
add('faculty-proposal', '03 Proposals and approval', 'Submitted proposal details', 'faculty', '/topics/${topic_id}', { records: ['topic_id'] });
add('head-project', '04 Project monitoring', 'Head project details', 'research_head', '/topics/${topic_id}', { records: ['topic_id'] });
add('secretary-project', '04 Project monitoring', 'Secretary project and monitoring reports', 'research_secretary', '/topics/${topic_id}', { records: ['topic_id'] });
add('monitoring-document', '08 Documents and generation', 'Download a submitted Monitoring Tool', 'research_secretary', '/progress-reports/${report_id}/monitoring-tool/pdf', { records: ['report_id'], binary: true });
add('forward-monitoring', '04 Project monitoring', 'Secretary forwards a submitted Monitoring Tool', 'research_secretary', '/research-secretary/monitoring-reports/${report_id}/review', { method: 'PATCH', write: true, records: ['topic_id', 'report_id'], payload: true, status: [302, 303], location: '/topics/${topic_id}', referer: '/topics/${topic_id}' });
add('accept-monitoring', '04 Project monitoring', 'Head accepts a forwarded Monitoring Tool', 'research_head', '/research-head/progress-reports/${report_id}', { method: 'PATCH', write: true, records: ['topic_id', 'report_id'], payload: true, status: [302, 303], location: '/topics/${topic_id}', referer: '/topics/${topic_id}' });

export { operations };
export const payloadExamples = {
    'create-draft': { project_title:'JMETER TEST ${run_id}-${user_index}' },
    'create-call': { title:'JMETER TEST ${run_id}-${user_index}', academic_year:'2026-2027', status:'draft', opens_at:'2027-01-01', closes_at:'2027-02-01', max_active_research_per_faculty:1 },
    'submit-proposal': {},
    'submit-monitoring': {},
    'save-budget': { budget_utilization:[{type:'Purchase Request',details:'Synthetic test',amount_requested:100,actual_amount:50,remarks:''},{type:'Cash Advance',details:'',amount_requested:0,actual_amount:0,remarks:''},{type:'Request of Payment',details:'',amount_requested:0,actual_amount:0,remarks:''}] },
    'create-outcome': {title:'JMETER TEST ${run_id}-${user_index}',team_members:'Synthetic test record',funding_source:'unfunded',labels:[]},
    'assistant-chat': {messages:[{role:'user',content:'Explain how to prepare a research proposal outline.'}]},
};
