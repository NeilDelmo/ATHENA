<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\User;
use App\Services\ImagePreviewService;
use App\Services\LiteratureDocumentTextExtractor;
use App\Services\ProposalFormVerifier;
use App\Services\ResearchAssistantDocumentService;
use App\Services\ResearchManualIndexer;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function capacityExtractionAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function capacityExtractionFixture(array $context, string $key): array
{
    $fixtures = $context['fixtures']['fixtures'] ?? $context['fixtures'];
    $fixture = $fixtures[$key] ?? null;
    capacityExtractionAssert(is_array($fixture), 'Missing fixture '.$key);
    $path = rtrim($context['fixture_directory'], '/').'/'.($fixture['filename'] ?? basename($fixture['path']));
    capacityExtractionAssert(is_file($path), 'Fixture not found: '.$path);
    capacityExtractionAssert(filesize($path) === $fixture['bytes'], 'Fixture size changed: '.$key);
    capacityExtractionAssert(hash_file('sha256', $path) === $fixture['sha256'], 'Fixture hash changed: '.$key);

    return [...$fixture, 'path' => $path];
}

function capacityExtractionRelativePath(string $path): string
{
    $root = str_replace('\\', '/', realpath(Storage::disk('local')->path('')) ?: '');
    $absolute = str_replace('\\', '/', realpath($path) ?: '');
    capacityExtractionAssert($root !== '' && str_starts_with($absolute, rtrim($root, '/').'/'), 'Fixtures must be inside disposable local storage.');

    return substr($absolute, strlen(rtrim($root, '/')) + 1);
}

function capacityExtractionEvidence(array $context, array $fixture, string $question): array
{
    $user = User::query()->findOrFail($context['faculty_id']);
    $draft = ProposalDraft::query()->findOrFail($context['draft_id']);
    capacityExtractionAssert((int) $draft->user_id === (int) $user->id, 'Synthetic faculty must own the synthetic draft.');
    $relative = capacityExtractionRelativePath($fixture['path']);
    $draft->documents()->updateOrCreate(['document_type' => 'detailed_proposal', 'position' => 0], [
        'source_data' => null,
        'file_path' => $relative,
        'original_filename' => basename($fixture['path']),
        'mime_type' => str_ends_with($fixture['path'], '.docx')
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf',
        'file_size' => $fixture['bytes'],
        'checksum' => $fixture['sha256'],
    ]);
    $service = app(ResearchAssistantDocumentService::class);
    $option = collect($service->availableDocuments($user, null, $draft->id))
        ->first(fn (array $document): bool => $document['filename'] === basename($fixture['path']));
    capacityExtractionAssert(is_array($option), 'Uploaded synthetic document is not selectable.');

    return $service->evidence($user, $option['token'], $question);
}

/** Run only in the parent's disposable application/database. Real GD and PDF tools; fake AI HTTP only. */
function capacityRunExtractionCase(string $case, array $context, string $outputDirectory): array
{
    capacityExtractionAssert(is_dir($outputDirectory) || mkdir($outputDirectory, 0775, true), 'Cannot create disposable output directory.');
    Http::preventStrayRequests();
    Process::swap(new Factory);
    config([
        'cache.default' => 'array',
        'services.openrouter.key' => null,
        'services.gemini.key' => null,
        'research_assistant.document.pdftotext_binary' => '/usr/bin/pdftotext',
        'research_assistant.document.pdftoppm_binary' => '/usr/bin/pdftoppm',
        'research_assistant.manual.pdftotext_binary' => '/usr/bin/pdftotext',
        'literature.full_text.pdftotext_binary' => '/usr/bin/pdftotext',
        'literature.evidence.pdfinfo_binary' => '/usr/bin/pdfinfo',
        'research_assistant.document.maximum_pdf_pages' => 200,
        'research_assistant.document.maximum_extracted_bytes' => 1024 * 1024,
        'research_assistant.document.maximum_docx_xml_bytes' => 12 * 1024 * 1024,
        'research_assistant.document.maximum_bytes' => 25 * 1024 * 1024,
        'research_assistant.document.maximum_context_bytes' => 12000,
        'research_assistant.document.extraction_timeout' => 20,
        'research_assistant.manual.extraction_timeout' => 120,
        'research_assistant.manual.chunk_characters' => 1800,
        'literature.evidence.maximum_pdf_kilobytes' => 8 * 1024,
        'literature.evidence.maximum_pdf_pages' => 200,
        'literature.evidence.maximum_characters' => 120000,
        'literature.evidence.extraction_timeout' => 20,
    ]);

    if (in_array($case, ['image-preview-12mp', 'image-preview-20mp', 'image-guard'], true)) {
        $key = match ($case) {
            'image-preview-12mp' => 'image_12mp',
            'image-preview-20mp' => 'image_20mp',
            default => 'image_guard',
        };
        $fixture = capacityExtractionFixture($context, $key);
        $relative = capacityExtractionRelativePath($fixture['path']);
        $preview = app(ImagePreviewService::class)->previewPath($relative);
        capacityExtractionAssert(hash_file('sha256', $fixture['path']) === $fixture['sha256'], 'Preview changed original.');
        if ($case === 'image-guard') {
            capacityExtractionAssert($fixture['width'] * $fixture['height'] > 20000000, 'Guard fixture must exceed twenty megapixels.');
            capacityExtractionAssert($preview === null, 'Oversized image was decoded instead of skipped.');

            return ['case' => $case, 'status' => 'guarded', 'input_bytes' => $fixture['bytes'], 'pixels' => $fixture['width'] * $fixture['height']];
        }
        // The production memory guard may legitimately retain the original under constrained PHP memory.
        if ($preview === null) {
            return ['case' => $case, 'status' => 'original_retained', 'input_bytes' => $fixture['bytes'], 'php_memory_limit' => ini_get('memory_limit')];
        }
        $absolute = Storage::disk('local')->path($preview);
        $dimensions = getimagesize($absolute);
        capacityExtractionAssert($dimensions !== false && $dimensions[2] === IMAGETYPE_WEBP, 'Preview is not valid WebP.');
        capacityExtractionAssert(max($dimensions[0], $dimensions[1]) === 1600, 'Preview dimension is incorrect.');
        capacityExtractionAssert(filesize($absolute) < $fixture['bytes'], 'Preview is not smaller than source.');

        return ['case' => $case, 'status' => 'converted', 'input_bytes' => $fixture['bytes'], 'preview_bytes' => filesize($absolute), 'width' => $dimensions[0], 'height' => $dimensions[1], 'preview_path' => $preview];
    }

    if (in_array($case, ['literature-200', 'literature-dense-200'], true)) {
        $fixture = capacityExtractionFixture($context, $case === 'literature-200' ? 'pdf_200' : 'pdf_dense_200');
        $document = app(LiteratureDocumentTextExtractor::class)->extract($fixture['path']);
        $coverage = $document['coverage'];
        capacityExtractionAssert($coverage['total_pages'] === 200, 'Real pdfinfo did not identify 200 pages.');
        if ($case === 'literature-200') {
            capacityExtractionAssert($coverage['complete'] === true && count($document['pages']) === 200, 'Ordinary 200-page paper was not completely extracted.');
            capacityExtractionAssert(str_contains($document['pages'][199]['text'], 'CAPACITY-END-PAGE-200'), 'Last page marker lost.');
        } else {
            capacityExtractionAssert($coverage['complete'] === false && $coverage['truncated'] === true, 'Dense paper did not trigger extraction truncation.');
            capacityExtractionAssert($coverage['characters'] === 120000, 'Dense extraction character budget is incorrect.');
        }

        return ['case' => $case, 'input_bytes' => $fixture['bytes'], 'coverage' => $coverage];
    }

    if (in_array($case, ['assistant-pdf-200', 'assistant-docx-5m', 'assistant-docx-12m', 'assistant-vision-2pages'], true)) {
        $key = match ($case) {
            'assistant-pdf-200' => 'pdf_200',
            'assistant-docx-5m' => 'docx_5m',
            'assistant-docx-12m' => 'docx_12m',
            default => 'scan_2pages',
        };
        $fixture = capacityExtractionFixture($context, $key);
        $visual = $case === 'assistant-vision-2pages';
        $temporaryRoot = storage_path('app/private/assistant-document-images');
        $before = glob($temporaryRoot.'/*') ?: [];
        $evidence = capacityExtractionEvidence($context, $fixture,
            $visual ? 'Explain the diagrams on pages 1 and 2.' : 'Summarize this document.');
        $coverage = $evidence['document']['coverage'];
        capacityExtractionAssert(count($evidence['sources']) > 0, 'Evidence contains no sources.');
        capacityExtractionAssert(strlen($evidence['prompt']) <= 12000, 'Evidence prompt exceeds byte budget.');
        $images = [];
        if ($visual) {
            capacityExtractionAssert($coverage['visual_pages'] === [1, 2] && count($evidence['images']) === 2, 'Exactly two selected page images were not returned.');
            foreach ($evidence['images'] as $image) {
                $url = $image['image_url']['url'];
                capacityExtractionAssert(str_starts_with($url, 'data:image/png;base64,'), 'Page image is not inline PNG.');
                $bytes = base64_decode(substr($url, strlen('data:image/png;base64,')), true);
                capacityExtractionAssert(is_string($bytes) && strlen($bytes) <= 8 * 1024 * 1024, 'Invalid PNG bytes.');
                $dimensions = getimagesizefromstring($bytes);
                capacityExtractionAssert($dimensions !== false && $dimensions[2] === IMAGETYPE_PNG && max($dimensions[0], $dimensions[1]) <= 1800, 'Rendered PNG exceeds dimension limit.');
                $images[] = ['bytes' => strlen($bytes), 'width' => $dimensions[0], 'height' => $dimensions[1], 'sha256' => hash('sha256', $bytes)];
            }
            capacityExtractionAssert(array_diff(glob($temporaryRoot.'/*') ?: [], $before) === [], 'Assistant temporary image directory leaked.');
        } elseif ($case === 'assistant-pdf-200') {
            capacityExtractionAssert($coverage['extracted_pages'] === 200, 'Assistant did not extract 200 pages.');
        } else {
            capacityExtractionAssert($coverage['truncated'] === true, 'Large DOCX did not disclose extraction truncation.');
        }
        $summary = ['case' => $case, 'input_bytes' => $fixture['bytes'], 'prompt_bytes' => strlen($evidence['prompt']), 'source_count' => count($evidence['sources']), 'coverage' => $coverage, 'images' => $images];
        capacityExtractionAssert(file_put_contents($outputDirectory.'/'.$case.'-evidence.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) !== false, 'Could not save evidence summary.');

        return $summary;
    }

    if (in_array($case, ['signed-form-text', 'signed-form-scan'], true)) {
        $scan = $case === 'signed-form-scan';
        $fixture = capacityExtractionFixture($context, $scan ? 'scan_2pages' : 'work_plan');
        $temporaryRoot = storage_path('app/private/signing-verification');
        $before = glob($temporaryRoot.'/*') ?: [];
        if ($scan) {
            config(['services.openrouter.key' => 'synthetic-not-a-real-key', 'services.openrouter.vision_model' => 'synthetic', 'services.openrouter.base_url' => 'https://capacity.invalid/v1']);
            Http::fake(['https://capacity.invalid/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'legible' => true, 'form_heading' => 'MAJOR ACTIVITIES/WORK PLAN', 'reference_number' => 'BatStateU-FO-RES-02',
                'project_title' => 'Coastal Resilience Study', 'field_labels' => ['Total Duration', 'Planned Start', 'Planned End', 'Expected Output'],
            ], JSON_THROW_ON_ERROR)]]]])]);
        }
        $source = new ProposalVersionFile(['document_type' => ProposalVersionFile::TYPE_WORK_PLAN]);
        $verification = app(ProposalFormVerifier::class)->check(new UploadedFile($fixture['path'], basename($fixture['path']), 'application/pdf', null, true), $source, 'Coastal Resilience Study');
        capacityExtractionAssert($verification['status'] === 'matched' && $verification['method'] === ($scan ? 'scan_reader' : 'pdf_text'), 'Synthetic form did not match using expected method.');
        capacityExtractionAssert(array_diff(glob($temporaryRoot.'/*') ?: [], $before) === [], 'Signing image directory leaked.');
        $imageBytes = [];
        if ($scan) {
            $requests = Http::recorded();
            capacityExtractionAssert($requests->count() === 1, 'Scanned form must use exactly one fake AI request.');
            foreach ($requests->first()[0]['messages'][1]['content'] as $content) {
                if (($content['type'] ?? '') === 'image_url') {
                    $bytes = base64_decode(substr($content['image_url']['url'], strlen('data:image/png;base64,')), true);
                    capacityExtractionAssert(is_string($bytes) && getimagesizefromstring($bytes) !== false, 'Fake AI did not receive actual rendered PNG.');
                    $imageBytes[] = strlen($bytes);
                }
            }
            capacityExtractionAssert(count($imageBytes) === 2, 'Scan verifier did not prepare both pages.');
        }

        return ['case' => $case, 'input_bytes' => $fixture['bytes'], 'verification' => $verification, 'prepared_image_bytes' => $imageBytes, 'external_ai' => 'never_called'];
    }

    if (in_array($case, ['manual-1000pages', 'manual-ocr-json', 'manual-long-paragraph'], true)) {
        $pdf = capacityExtractionFixture($context, 'manual_1000');
        $json = $case === 'manual-1000pages' ? null : capacityExtractionFixture($context, $case === 'manual-ocr-json' ? 'ocr_pages' : 'ocr_long_paragraph');
        $directory = $outputDirectory.'/'.$case;
        $indexPath = $directory.'/index.json';
        $index = app(ResearchManualIndexer::class)->index($pdf['path'], 'Synthetic Capacity Manual', true, $json['path'] ?? null, $indexPath);
        $expectedPages = $json['pages'] ?? 1000;
        capacityExtractionAssert($index['page_count'] === $expectedPages && count($index['chunks']) > 0, 'Manual lost pages or index chunks.');
        $characters = 0;
        foreach ($index['chunks'] as $chunk) {
            $length = mb_strlen($chunk['content']);
            capacityExtractionAssert($length <= 1800 && $length > 0, 'Manual chunk exceeds character limit.');
            $characters += $length;
        }
        $last = $index['chunks'][array_key_last($index['chunks'])];
        $marker = $json === null ? 'CAPACITY-END-PAGE-1000' : 'CAPACITY-OCR-END-'.$expectedPages;
        capacityExtractionAssert(str_contains($last['content'], $marker), 'Manual lost final source text.');
        capacityExtractionAssert(is_file($indexPath) && hash_file('sha256', $directory.'/manual.pdf') === $pdf['sha256'], 'Manual index/source was not saved.');

        return ['case' => $case, 'source_bytes' => $pdf['bytes'], 'ocr_bytes' => $json['bytes'] ?? null, 'page_count' => $index['page_count'], 'chunk_count' => count($index['chunks']), 'indexed_characters' => $characters, 'unindexed_pages' => $index['unindexed_pages'], 'index_bytes' => filesize($indexPath), 'index_path' => $indexPath];
    }

    throw new InvalidArgumentException('Unknown extraction capacity case: '.$case);
}
