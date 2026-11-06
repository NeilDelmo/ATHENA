<?php

use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectArchive;
use App\Services\ResearchManualRetriever;
use App\Services\ScopusSourceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function capacityRunOtherCase(string $case, array $context, string $outputDirectory): array
{
    capacityDocAssert((string) env('CAPACITY_ENABLED') === '1', 'Disposable capacity environment required.');
    if (str_starts_with($case, 'manual-retrieve-')) {
        $indexPath = $outputDirectory.'/manual-ocr-json/index.json';
        capacityDocAssert(is_file($indexPath), 'Run the manual indexing case before retrieval.');
        config(['research_assistant.manual.index_path' => $indexPath]);
        if ($case === 'manual-retrieve-cold') {
            DB::table('cache')->where('key', 'like', '%research-manual:%')->delete();
        }
        $question = 'What does CAPACITY-OCR-END-1000 say about ethics approval and retention?';
        $sources = app(ResearchManualRetriever::class)->retrieve($question, [], $indexPath);
        capacityDocAssert(count($sources) > 0 && count($sources) <= 5, 'Manual retrieval returned no bounded evidence.');
        capacityDocAssert(str_contains(json_encode($sources, JSON_THROW_ON_ERROR), 'eighteen months'), 'Retrieved evidence lost the synthetic policy text.');

        return ['case' => $case, 'index_bytes' => filesize($indexPath), 'sources' => count($sources), 'response_bytes' => strlen(json_encode($sources, JSON_THROW_ON_ERROR))];
    }
    if (str_starts_with($case, 'scopus-')) {
        $count = (int) substr($case, 7);
        capacityDocAssert(in_array($count, [10000, 50000], true), 'Unsupported registry workload.');
        $registryPath = $outputDirectory.'/'.$case.'-registry.json';
        config(['services.scopus.source_list_path' => $registryPath]);
        $registry = new ScopusSourceRegistry;
        $metadata = $registry->import($context['fixture_directory'].'/'.$case.'.xlsx', 'https://example.test/synthetic-scopus.xlsx');
        $last = $registry->find((string) (10000000 + $count));
        capacityDocAssert(($last['title'] ?? '') === sprintf('Capacity Synthetic Journal %05d', $count), 'Last imported journal was lost.');
        $saved = json_decode(file_get_contents($registryPath), true, 512, JSON_THROW_ON_ERROR);
        capacityDocAssert(count($saved['sources']) === 2 * $count, 'Journal import lost ISSNs.');

        return ['case' => $case, 'journals' => $count, 'issns' => count($saved['sources']), 'registry_bytes' => filesize($registryPath), 'metadata' => $metadata];
    }
    if ($case === 'archive-complete') {
        $topic = TopicProposal::findOrFail($context['topic_id']);
        $faculty = User::findOrFail($context['faculty_id']);
        capacityDocAssert($topic->user_id === $faculty->id, 'Synthetic archive must be faculty-owned.');
        $disk = Storage::disk('local');
        $relative = fn (string $filename): string => 'capacity-output/'.$filename;
        $topic->update(['notice_to_proceed_path' => $relative('native-work_plan.pdf'), 'notice_to_proceed_original_filename' => 'synthetic-notice.pdf']);
        $version = $topic->versions()->firstOrFail();
        $version->update(['file_path' => $relative('native-detailed_proposal.pdf'), 'original_filename' => 'synthetic-proposal.pdf']);
        foreach (['work_plan', 'line_item_budget', 'expense_breakdown', 'curriculum_vitae', 'gad_checklist', 'initial_screening_form', 'detailed_proposal'] as $type) {
            $path = $relative('native-'.$type.'.pdf');
            capacityDocAssert($disk->exists($path), 'Missing real generated archive input.');
            $version->files()->updateOrCreate(['document_type' => $type, 'position' => 0], [
                'file_path' => $path, 'original_filename' => 'synthetic-'.$type.'.pdf', 'mime_type' => 'application/pdf',
                'file_size' => $disk->size($path), 'checksum' => hash_file('sha256', $disk->path($path)),
            ]);
        }
        foreach ($context['image_paths'] as $index => $path) {
            $topic->projectDocuments()->updateOrCreate(['title' => 'Capacity photo '.$index], [
                'uploaded_by' => $faculty->id, 'category' => 'supporting_files', 'file_path' => $path,
                'original_filename' => basename($path), 'mime_type' => 'image/jpeg', 'file_size' => $disk->size($path),
            ]);
        }
        $monitoring = $topic->progressReports()->firstOrFail();
        $monitoring->update(['official_pdf_path' => null, 'work_plan' => [], 'budget_utilization' => []]);
        foreach (['progress' => 'progress-images.pdf', 'terminal' => 'terminal-images-tables.pdf'] as $type => $filename) {
            $path = $relative($filename);
            capacityDocAssert($disk->exists($path), 'Missing real report archive input.');
            $topic->narrativeReports()->updateOrCreate(['report_type' => $type, 'version_number' => 1], [
                'submitted_by' => $faculty->id, 'submission_date' => '2026-09-30', 'researchers' => $faculty->name,
                'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31', 'budget' => 3600,
                'funding_agency' => 'Synthetic University', 'accomplishment_summary' => 'Synthetic research complete.',
                'introduction' => 'Synthetic research.', 'objectives' => 'Synthetic objective.',
                'methodology' => 'Synthetic method.', 'results_discussion' => 'Synthetic results.', 'photos' => [],
                'submitted_at' => now(), 'submission_status' => 'submitted', 'review_stage' => 'head',
                'official_pdf_path' => $path, 'official_pdf_filename' => $filename, 'official_pdf_checksum' => hash_file('sha256', $disk->path($path)),
            ]);
        }
        $response = app(ProjectArchive::class)->download($topic->fresh(), $faculty);
        $temporary = $response->getFile()->getPathname();
        $destination = $outputDirectory.'/archive-complete.zip';
        capacityDocAssert(copy($temporary, $destination), 'Could not retain synthetic archive.');
        unlink($temporary);
        $zip = new ZipArchive;
        capacityDocAssert($zip->open($destination, ZipArchive::CHECKCONS) === true, 'Archive is not a valid ZIP.');
        try {
            $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            capacityDocAssert($manifest['missing_files'] === 0 && count($manifest['files']) === 23, 'Archive omitted submitted records: '.json_encode($manifest));
            $generated = 0;
            foreach ($manifest['files'] as $file) {
                $bytes = $zip->getFromName($file['archive_path']);
                capacityDocAssert(is_string($bytes) && strlen($bytes) === $file['size'] && hash('sha256', $bytes) === $file['sha256'], 'Archive bytes differ from its manifest.');
                $generated += str_contains($file['note'] ?? '', 'generated') ? 1 : 0;
            }
            capacityDocAssert($generated === 1, 'Archive must perform one real missing monitoring-PDF conversion.');
        } finally {
            $zip->close();
        }

        return ['case' => $case, 'files' => count($manifest['files']), 'generated_pdfs' => $generated, 'archive_bytes' => filesize($destination), 'sha256' => hash_file('sha256', $destination)];
    }

    throw new InvalidArgumentException('Unknown additional capacity case '.$case);
}
