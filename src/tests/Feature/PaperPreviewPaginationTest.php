<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\NoticeToProceedDataService;
use App\Support\CurriculumVitaeData;
use App\Support\DetailedProposalData;
use App\Support\ExpenseBreakdownData;
use App\Support\GADChecklistData;
use App\Support\LineItemBudgetData;
use App\Support\WorkPlanData;

// These rendered fixtures also exercise pagination with each real paper layout in the browser.
test('paper previews expose their sheets for shared pagination', function (string $paper) {
    $this->withoutVite();
    $paragraphs = implode("\n\n", array_map(fn (int $index): string => "Study {$index}: Community monitoring records explain the observations and findings in this research project.", range(1, 100)));
    $base = ['project_title' => 'Pagination coverage', 'project_leader' => 'Alexandra Reyes'];
    $person = User::factory()->make(['name' => 'Alexandra Reyes']);
    $topic = (new TopicProposal($base))->setRelation('user', $person);
    $narrative = (new ProjectNarrativeReport([
        'introduction' => $paragraphs, 'objectives' => 'Evaluate the monitoring process.',
        'rationale' => 'Community observations', 'accomplishments' => [], 'photos' => [],
        'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
        'terminal_data' => [], 'methodology' => 'Field observation', 'results_discussion' => 'Monitoring findings',
    ]))->setRelation('topic', $topic);
    [$view, $data] = match ($paper) {
        'notice-to-proceed' => ['topics.notice-to-proceed-preview', ['notice' => app(NoticeToProceedDataService::class)->documentValues([
            ...$base, 'notice_date' => '2026-01-01', 'researcher_names' => ['Alexandra Reyes'], 'campus_line' => 'ARASOF-Nasugbu',
            'resolution_number' => '01', 'resolution_year' => '2026', 'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31',
            'approved_duration_months' => 12, 'approved_budget' => 10000,
            'issuing_officer_name' => 'Officer', 'issuing_officer_title' => 'Vice Chancellor', 'issuing_officer_committee_role' => 'Member, LREC',
            'verifying_officer_name' => 'Reviewer', 'verifying_officer_title' => 'Vice President', 'verifying_officer_committee_role' => 'Chair, LREC',
        ])]],
        'coordinator-report' => ['research_coordinator.report-print', ['embedded' => true, 'year' => 2026, 'quarter' => 1, 'report' => null, 'coordinator' => $person, 'period' => ['start' => now()->startOfYear(), 'end' => now()->startOfYear()->addMonths(2)], 'reportData' => ['presentations' => [], 'publications' => [], 'research' => []]]],
        'detailed-proposal' => ['faculty.detailed-proposals.preview', ['detailedProposal' => DetailedProposalData::fromValidated([...$base, 'related_literature' => $paragraphs])]],
        'work-plan' => ['faculty.work-plans.preview', ['workPlan' => WorkPlanData::fromValidated([...$base, 'total_duration_months' => 36])]],
        'line-item-budget' => ['faculty.line-item-budgets.preview', ['lineItemBudget' => LineItemBudgetData::fromValidated($base)]],
        'expense-breakdown' => ['faculty.expense-breakdowns.preview', ['expenseBreakdown' => ExpenseBreakdownData::fromValidated($base)]],
        'curriculum-vitae' => ['faculty.curriculum-vitae.preview', ['curriculumVitae' => CurriculumVitaeData::fromValidated(['people' => CurriculumVitaeData::seedPeople(['Alexandra Reyes', 'Andrea Santos'])])]],
        'gad-checklist' => ['faculty.gad-checklist.preview', ['gadChecklist' => GADChecklistData::fromValidated($base)]],
        'initial-screening' => ['faculty.initial-screening-form.preview', ['screeningForm' => [...$base, 'order_of_submission' => 'first_submission']]],
        'comment-response' => ['faculty.comment-response-form.preview', ['topic' => 1, 'embedded' => true, 'commentResponseForm' => [...$base, 'form_source' => 'proposal', 'review_id' => null, 'form_label' => 'Comment Response', 'staff' => [], 'feedback' => [['comment' => $paragraphs, 'response' => 'Revised.', 'remarks' => '']]]]],
        'monitoring' => ['faculty.monitoring-tools.preview', ['report' => (new ProjectProgressReport(['reporting_date' => '2026-03-31', 'progress_percentage' => 25, 'work_plan' => [], 'budget_utilization' => []]))->setRelation('topic', $topic)->setRelation('submitter', $person)]],
        'progress-report' => ['faculty.progress-reports.preview', ['report' => $narrative]],
        'terminal-report' => ['faculty.progress-reports.terminal-preview', ['report' => $narrative]],
    };
    $html = view($view, $data)->render();
    expect($html)->toContain('data-paper-sheet');
    if (getenv('ATHENA_EXPORT_PAGINATION') === '1') {
        file_put_contents(storage_path('framework/testing/pagination-'.$paper.'.html'), $html);
    }
})->with(['detailed-proposal', 'work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae', 'gad-checklist', 'initial-screening', 'comment-response', 'monitoring', 'progress-report', 'terminal-report', 'notice-to-proceed', 'coordinator-report']);
