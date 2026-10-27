<?php

use App\Exceptions\ResearchAssistantDocumentException;
use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\ResearchAssistantDocumentService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'research_assistant.document.maximum_pdf_pages' => 200,
        'literature.evidence.pdfinfo_binary' => 'pdfinfo',
        'research_assistant.document.pdftoppm_binary' => 'pdftoppm',
        'research_assistant.document.pdftotext_binary' => 'pdftotext',
    ]);
    Storage::fake('local');
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Role::firstOrCreate(['name' => 'faculty']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('faculty');
    $this->actingAs($this->owner);
    $call = ResearchCall::create(['title' => 'Document vision fixture', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->owner->id, 'research_call_id' => $call->id,
        'project_title' => 'Visual reading fixture', 'project_leader' => $this->owner->name]);
    Storage::disk('local')->put('proposals/visual-reading.pdf', '%PDF scanned fixture');
    $this->document = $this->draft->documents()->create(['document_type' => 'detailed_proposal',
        'position' => 0, 'file_path' => 'proposals/visual-reading.pdf', 'original_filename' => 'visual-reading.pdf']);
    $this->service = app(ResearchAssistantDocumentService::class);
    $this->token = $this->service->availableDocuments($this->owner, null, $this->draft->id)[0]['token'];
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB2sAAAAASUVORK5CYII=');
    $this->renderDirectories = [];
    $this->fakePages = function (int $pageCount = 5, ?Closure $render = null): void {
        Process::fake(function (PendingProcess $process) use ($pageCount, $render) {
            if ($process->command[0] === 'pdfinfo') {
                return Process::result(output: "Pages:          {$pageCount}\n");
            }
            if ($process->command[0] === 'pdftoppm') {
                $output = end($process->command).'.png';
                $this->renderDirectories[] = dirname($output);
                if ($render) {
                    return $render($process, $output);
                }
                File::put($output, $this->png);

                return Process::result();
            }
            if ($process->command[0] === 'pdftotext') {
                return Process::result(output: 'Selectable textual research background provides enough ordinary prose for document analysis.');
            }

            throw new RuntimeException('Unexpected process in document vision test.');
        });
    };
});

test('explicit visual reading supplies only the requested PDF page images with exact citations', function () {
    ($this->fakePages)();
    $evidence = $this->service->evidence($this->owner, $this->token, 'Explain the diagrams on pages 2 and 5.');

    expect($evidence['images'] ?? [])->toHaveCount(2)
        ->and($evidence['images'][0])->toBe(['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($this->png)]])
        ->and(array_column($evidence['sources'], 'reference'))->toBe(['Selected document page 2', 'Selected document page 5'])
        ->and(array_column($evidence['sources'], 'page_start'))->toBe([2, 5])
        ->and($evidence['document']['coverage']['visual_pages'])->toBe([2, 5])
        ->and($evidence['document']['coverage']['total_pdf_pages'])->toBe(5)
        ->and(implode(' ', $evidence['document']['coverage']['limitations']))->toContain('Only', '2', '5')
        ->and($evidence['prompt'])->toContain('[Selected document page 2]', '[Selected document page 5]', 'untrusted')
        ->and(json_encode([$evidence['prompt'], $evidence['document'], $evidence['sources']]))->not->toContain('data:image', 'app/private', 'base64')
        ->and(strlen($evidence['prompt']))->toBeLessThanOrEqual(12000);
    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm', 2);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm'
        && $process->command[array_search('-f', $process->command) + 1] === '5'
        && $process->command[array_search('-l', $process->command) + 1] === '5'
        && in_array('1800', $process->command, true));
    foreach ($this->renderDirectories as $directory) {
        expect(is_dir($directory))->toBeFalse();
    }
    Http::assertNothingSent();
});

test('visual page selection accepts page abbreviations and a bounded range', function (string $question, array $pages) {
    ($this->fakePages)();
    $evidence = $this->service->evidence($this->owner, $this->token, $question);
    expect($evidence['document']['coverage']['visual_pages'] ?? null)->toBe($pages)
        ->and($evidence['images'])->toHaveCount(count($pages));
})->with([
    ['Read the figure on p. 2.', [2]],
    ['Explain the charts on pages 2-3.', [2, 3]],
    ['Read the images on pp. 2–3.', [2, 3]],
    ['Compare the diagrams on page 2 and page 5.', [2, 5]],
]);

test('visual requests without PDF page numbers ask for a bounded explicit selection', function () {
    expect(fn () => $this->service->evidence($this->owner, $this->token, 'What does figure 3 show?'))
        ->toThrow(ResearchAssistantDocumentException::class, 'page');
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('invalid or excessive PDF page selections never render arbitrary replacement pages', function (string $question) {
    ($this->fakePages)();
    expect(fn () => $this->service->evidence($this->owner, $this->token, $question))
        ->toThrow(ResearchAssistantDocumentException::class);
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm');
    Http::assertNothingSent();
})->with([
    'page zero' => ['Read the image on page 0.'],
    'negative page' => ['Read the image on page -1.'],
    'outside document' => ['Read the image on page 6.'],
    'over server limit' => ['Read the image on page 201.'],
    'three listed pages' => ['Read the diagrams on pages 1, 2 and 3.'],
    'three page range' => ['Read the charts on pages 1-3.'],
    'descending range' => ['Read the images on pages 3-2.'],
    'fractional second page' => ['Read the diagrams on pages 2 and 3.5.'],
    'compound range' => ['Read the diagrams on pages 1-2-3.'],
    'Oxford comma list' => ['Read the diagrams on pages 1, 2, and 3.'],
    'alphanumeric page' => ['Read the diagram on page 2e1.'],
    'fractional slash page' => ['Read the diagrams on pages 2/3.'],
]);

test('visual reading respects the configured PDF page safety limit', function () {
    config(['research_assistant.document.maximum_pdf_pages' => 2]);
    ($this->fakePages)();
    expect(fn () => $this->service->evidence($this->owner, $this->token, 'Read the diagram on page 3.'))
        ->toThrow(ResearchAssistantDocumentException::class);
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm');
});

test('ordinary numerical figures and explicitly skipped images keep text reading', function (string $question) {
    ($this->fakePages)();
    $evidence = $this->service->evidence($this->owner, $this->token, $question);
    expect($evidence)->not->toHaveKey('images');
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pdftotext');
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm');
})->with([
    ['What budget figures are included in the document?'],
    ['Figure out what the research background says.'],
    ['Summarize the research text on page 2; do not read images.'],
]);

test('visual reading fails explicitly and cleans temporary images when rendering fails', function (string $failure) {
    ($this->fakePages)(5, function (PendingProcess $process, string $output) use ($failure) {
        if ($failure === 'oversized') {
            File::put($output, str_repeat('x', 8 * 1024 * 1024 + 1));
        } elseif ($failure === 'second page') {
            File::put($output, $this->png);
            if ($process->command[array_search('-f', $process->command) + 1] === '3') {
                return Process::result(exitCode: 1);
            }
        }

        return Process::result(exitCode: $failure === 'failed' ? 1 : 0);
    });
    expect(fn () => $this->service->evidence($this->owner, $this->token, 'Read the diagrams on pages 2 and 3.'))
        ->toThrow(ResearchAssistantDocumentException::class);
    foreach ($this->renderDirectories as $directory) {
        expect(is_dir($directory))->toBeFalse();
    }
    Http::assertNothingSent();
})->with(['failed', 'missing output', 'oversized', 'second page']);

test('visual reading requires a verifiable PDF page count before rendering', function () {
    Process::fake(['*' => Process::result(output: 'PDF metadata unavailable', exitCode: 1)]);
    expect(fn () => $this->service->evidence($this->owner, $this->token, 'Read the diagram on page 2.'))
        ->toThrow(ResearchAssistantDocumentException::class, 'page count');
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'pdftoppm');
});

test('DOCX visual reading explains how to select a PDF page without claiming image coverage', function () {
    Storage::disk('local')->put('proposals/visual-reading.docx', 'DOCX fixture');
    $this->document->update(['file_path' => 'proposals/visual-reading.docx', 'original_filename' => 'visual-reading.docx']);
    $token = $this->service->availableDocuments($this->owner, null, $this->draft->id)[0]['token'];
    expect(fn () => $this->service->evidence($this->owner, $token, 'Read the diagram on page 2.'))
        ->toThrow(ResearchAssistantDocumentException::class, 'PDF');
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('PDF page images remain private after document access is revoked', function () {
    $this->draft->update(['user_id' => User::factory()->create()->id]);
    expect(fn () => $this->service->evidence($this->owner, $this->token, 'Read the diagram on page 2.'))
        ->toThrow(ResearchAssistantDocumentException::class, 'unavailable for your account');
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('document chat sends requested page images once to the vision model and saves only coverage', function () {
    config([
        'services.openrouter.key' => null,
        'services.gemini.key' => 'test-key',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'services.gemini.model' => 'text-model',
        'services.gemini.vision_model' => 'vision-model',
    ]);
    ($this->fakePages)();
    Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => [
        'content' => 'The diagram shows a labeled workflow. [Selected document page 2]',
    ], 'finish_reason' => 'stop']]])]);
    $question = 'Explain the scanned diagram on page 2.';
    $response = $this->postJson(route('research-support.chat'), [
        'action' => ['type' => 'analyze_document', 'document_token' => $this->token],
        'messages' => [['role' => 'user', 'content' => $question]],
        'context' => ['proposal_draft_id' => $this->draft->id],
    ])->assertSuccessful()->assertJsonPath('document_reading.coverage.visual_pages', [2]);
    expect(collect($response->json('sources'))->pluck('reference')->all())->toContain('Selected document page 2')
        ->and($response->getContent())->not->toContain('data:image', base64_encode($this->png));
    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($question): bool {
        $messages = $request['messages'];
        $message = end($messages);

        return $request['model'] === 'vision-model'
            && $message['role'] === 'user'
            && $message['content'][0] === ['type' => 'text', 'text' => $question]
            && $message['content'][1]['type'] === 'image_url'
            && str_starts_with($message['content'][1]['image_url']['url'], 'data:image/png;base64,');
    });
    $this->postJson(route('research-support.history.save'), [
        'context' => ['proposal_draft_id' => $this->draft->id],
        'messages' => [
            ['role' => 'user', 'content' => $question],
            ['role' => 'assistant', 'content' => 'A labeled workflow. [Selected document page 2]',
                'document_reading' => $response->json('document_reading')],
        ],
    ])->assertSuccessful();
    $history = ResearchAssistantConversation::query()->where('user_id', $this->owner->id)->latest('id')->firstOrFail()->messages;
    expect($history[1]['document_reading']['coverage']['visual_pages'])->toBe([2])
        ->and(json_encode($history, JSON_THROW_ON_ERROR))->not->toContain('data:image', base64_encode($this->png));
});
