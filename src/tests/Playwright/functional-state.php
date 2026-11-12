<?php

use App\Models\ProjectDocument;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;

$fixtures = $manifest['functional'];
$report = ProjectProgressReport::findOrFail($fixtures['reportId']);
$spent = collect($report->budget_utilization)->sum('actual_amount');

return [
    'calls' => ResearchCall::orderBy('id')->get(['id', 'title', 'description', 'status', 'academic_year', 'opens_at', 'closes_at'])->toArray(),
    'draft' => ProposalDraft::findOrFail($fixtures['draftId'])->only(['id', 'project_title', 'status', 'user_id', 'research_call_id', 'lock_version']),
    'project' => TopicProposal::findOrFail($fixtures['projectId'])->only(['id', 'status', 'project_status', 'user_id']),
    'projectTransitions' => TopicProposal::findOrFail($fixtures['projectId'])->stageTransitions()->count(),
    'projectVersions' => TopicProposal::findOrFail($fixtures['projectId'])->versions()->count(),
    'otherAllocation' => (float) TopicProposal::findOrFail($fixtures['otherProjectId'])->estimated_budget,
    'budget' => ['spent' => $spent, 'remaining' => 100000 - $spent, 'token' => $report->budgetToken(), 'rows' => $report->budget_utilization],
    'report' => $report->only(['id', 'topic_id', 'submitted_by', 'reporting_date', 'submission_status']),
    'documents' => ProjectDocument::where('topic_id', $fixtures['projectId'])->get(['id', 'topic_id', 'original_filename', 'checksum', 'file_size'])->toArray(),
];
