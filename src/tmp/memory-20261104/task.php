<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\ProposalPdfPreparation;
use App\Services\ProposalRevisionSectionMap;
use App\Support\ProposalDraftReadiness;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/workload-documents.php';
require_once __DIR__.'/workload-extraction.php';
require_once __DIR__.'/workload-other.php';

function capacityRunTask(string $case): array
{
    $context = json_decode(file_get_contents(storage_path('app/private/capacity-context.json')), true, 512, JSON_THROW_ON_ERROR);
    $directory = storage_path('app/private/capacity-output');
    $started = hrtime(true);
    if ($case === 'grow-1000') {
        require_once __DIR__.'/seed-web.php';
        capacityWebSeedProjects(
            User::findOrFail($context['faculty_id']),
            User::findOrFail($context['head_id']),
            User::findOrFail($context['secretary_id']),
            ResearchCall::findOrFail($context['call_id']),
            1000,
        );
        $context['project_count'] = TopicProposal::count();
        file_put_contents(storage_path('app/private/capacity-context.json'), json_encode($context, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $result = ['case' => $case, 'projects' => $context['project_count']];
    } elseif ($case === 'queue-request') {
        $draft = ProposalDraft::with('documents', 'owner', 'researchCall')->findOrFail($context['draft_id']);
        $errors = app(ProposalDraftReadiness::class)->errors($draft);
        if ($errors !== []) {
            throw new RuntimeException('Queue fixture is invalid: '.json_encode($errors));
        }
        if (app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft)) {
            $draft->documents()->update(['mime_type' => null]);
        }
        app(ProposalPdfPreparation::class)->request($draft->id, true);
        $result = ['case' => $case, 'state' => $draft->refresh()->pdf_preparation_state];
    } elseif ($case === 'queue-status') {
        $draft = ProposalDraft::with('documents', 'owner', 'researchCall')->findOrFail($context['draft_id']);
        $result = ['case' => $case, 'state' => $draft->pdf_preparation_state,
            'status' => app(ProposalPdfPreparation::class)->status($draft),
            'jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count()];
    } elseif (in_array($case, ['native-seven', 'native-seven-cold'], true)) {
        if ($case === 'native-seven-cold') {
            config(['cache.default' => 'array']);
        }
        $documents = [];
        foreach (['work_plan', 'line_item_budget', 'expense_breakdown', 'curriculum_vitae', 'gad_checklist', 'initial_screening_form', 'detailed_proposal'] as $type) {
            $documents[$type] = ['contents' => file_get_contents(config($type.'.template_path')), 'format' => $type === 'expense_breakdown' ? 'xlsx' : 'docx'];
        }
        $pdfs = app(LibreOfficeDocumentPdfConverter::class)->convertDocuments($documents);
        $summaries = [];
        foreach ($pdfs as $type => $pdf) {
            if (! str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('Invalid PDF '.$type);
            }
            $path = $directory.'/native-'.$type.'.pdf';
            file_put_contents($path, $pdf);
            $sections = app(ProposalRevisionSectionMap::class)->fromPdf($pdf, $type);
            $summaries[$type] = ['bytes' => strlen($pdf), 'sections' => count($sections)];
        }
        if (count($summaries) !== 7) {
            throw new RuntimeException('Missing native benchmark PDFs.');
        }
        $result = ['case' => $case, 'files' => $summaries];
    } elseif ($case === 'terminal-240') {
        config(['document_pdf.timeout_seconds' => 240]);
        $result = capacityRunDocumentCase('terminal-images-tables', $context, $directory.'/terminal-240');
        $result['case'] = $case;
        $result['converter_timeout_seconds'] = 240;
    } elseif (in_array($case, ['progress-highres-6', 'progress-highres-12', 'progress-30photos'], true)) {
        $photos = json_decode(file_get_contents(storage_path('app/private/capacity-photos/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $kind = $case === 'progress-30photos' ? 'normal' : 'highres';
        $count = match ($case) {
            'progress-highres-6' => 6,
            'progress-highres-12' => 12,
            default => 30,
        };
        $selected = array_slice($photos[$kind], 0, $count);
        $context['image_paths'] = array_column($selected, 'relative');
        $context['expected_image_count'] = $count;
        $context['expected_image_dimensions'] = [$selected[0]['width'], $selected[0]['height']];
        $result = capacityRunDocumentCase('progress-images', $context, $directory.'/'.$case.'-'.bin2hex(random_bytes(4)));
        $result['case'] = $case;
        $result['total_image_pixels'] = $count * $selected[0]['width'] * $selected[0]['height'];
    } elseif (in_array($case, ['proposal-large', 'progress-images', 'terminal-images-tables', 'quarterly-evidence', 'rms-500', 'rms-1500', 'expense-100'], true)) {
        $result = capacityRunDocumentCase($case, $context, $directory);
    } elseif (str_starts_with($case, 'manual-retrieve-') || str_starts_with($case, 'scopus-') || $case === 'archive-complete') {
        $result = capacityRunOtherCase($case, $context, $directory);
    } else {
        $result = capacityRunExtractionCase($case, $context, $directory);
    }
    $result['elapsed_seconds'] = (hrtime(true) - $started) / 1e9;
    $result['php_peak_mib'] = memory_get_peak_usage(true) / 1048576;
    file_put_contents($directory.'/'.$case.'-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $result;
}
