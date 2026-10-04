<?php

namespace App\Support;

use App\Models\ProposalSignatory;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;

class ResearchHeadScreeningData
{
    public const DOCUMENTS = [
        'detailed_proposal' => 'Detailed Proposal (DP)',
        'work_plan' => 'Major Activities and Work Plan (WP)',
        'line_item_budget' => 'Line-Item Budget (LIB)',
        'curriculum_vitae' => 'Curriculum Vitae (CV)',
        'gad_checklist' => 'GAD Checklist',
    ];

    public const CRITERIA = [
        'documents' => ['label' => 'Complete Documents Submitted', 'scores' => [30 => 'Satisfactory', 15 => 'Needs Minor Revision', 5 => 'Needs Major Revision']],
        'alignment' => ['label' => 'Alignment to University’s Research Agenda', 'scores' => [30 => 'Satisfactory', 15 => 'Needs Minor Revision', 5 => 'Needs Major Revision']],
        'content' => ['label' => 'Content', 'scores' => [40 => 'Satisfactory', 20 => 'Needs Minor Revision', 10 => 'Needs Major Revision']],
    ];

    /** @return array<string, mixed> */
    public function forVersion(ProposalVersion $version): array
    {
        $version->loadMissing(['files', 'topic.user', 'submitter']);
        $source = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)?->source_data ?? [];
        $proposal = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)?->source_data ?? [];
        $budget = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)?->source_data ?? [];
        $defaults = [
            'project_title' => $source['project_title'] ?? $version->title ?? $version->topic->title,
            'project_leader' => $source['project_leader'] ?? $proposal['project_leader'] ?? $version->submitter?->name ?? $version->topic->user?->name,
            'order_of_submission' => $source['order_of_submission'] ?? ($version->version_number === 1 ? InitialScreeningSubmissionOrder::FIRST_SUBMISSION : InitialScreeningSubmissionOrder::REVISED_WITH_MINOR_CHANGES),
            'level_of_call' => $source['level_of_call'] ?? $budget['level_of_call'] ?? LineItemBudgetData::DEFAULT_LEVEL_OF_CALL,
            'requested_budget' => $version->estimated_budget,
            'duration_months' => $version->estimated_duration_months,
            'researcher_count' => 1 + count($proposal['staff'] ?? []),
            'department' => $proposal['proponent_department'] ?? '',
            'college' => $proposal['proponent_college'] ?? $version->topic->user?->college ?? '',
            'campus' => $proposal['proponent_campus'] ?? '',
            'screening_head' => $source['screening_head'] ?? auth()->user()?->name ?? '',
            'screening_center' => $source['screening_center'] ?? '',
            'screening_verifier' => $source['screening_verifier'] ?? '',
            'documents' => collect(self::DOCUMENTS)->mapWithKeys(fn (string $label, string $type): array => [$type => ['attached' => $version->files->contains('document_type', $type), 'pages' => null]])->all(),
            'scores' => [], 'recommended_action' => null, 'narrative_evaluation' => '',
        ];

        $data = [
            ...$defaults,
            ...($version->research_head_screening ?? []),
            'screening_head' => ProposalSignatory::defaultSelections()['screening_head']['name'],
            'screening_verifier' => ProposalSignatory::defaultSelections()['screening_verifier']['name'],
        ];

        if (blank($data['screening_center'] ?? null)) {
            $data['screening_center'] = (string) config('research_signatories.center_head');
        }

        return $data;
    }
}
