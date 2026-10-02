<?php

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProposalSignatureWorkflow;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::set('local', Storage::fake('signed-form-verification-'.getmypid()));
    $this->withoutVite();
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    config(['services.openrouter.key' => null, 'services.gemini.key' => null, 'proposal_signing.demo_mode' => false]);

    foreach (['faculty', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->owner = User::factory()->create(['college' => 'CICS']);
    $this->owner->assignRole('faculty');
    $this->staff = User::factory()->create(['college' => 'CICS']);
    $this->staff->assignRole('research_coordinator');
    $this->topic = TopicProposal::create([
        'user_id' => $this->owner->id,
        'title' => 'Coastal Resilience Study',
        'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
    ]);
    $this->version = $this->topic->versions()->create([
        'version_number' => 1, 'title' => $this->topic->title,
        'submitted_by' => $this->owner->id, 'submission_type' => 'initial',
        'file_path' => 'source.pdf', 'original_filename' => 'source.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100,
    ]);
    foreach (['detailed_proposal', 'work_plan', 'line_item_budget'] as $type) {
        Storage::disk('local')->put('original/'.$type.'.pdf', '%PDF-1.4 original');
        $this->version->files()->create([
            'document_type' => $type, 'file_path' => 'original/'.$type.'.pdf',
            'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf',
            'source_data' => ['project_title' => $this->topic->title],
        ]);
    }
    $this->actingAs($this->staff)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
});

function signingFormText(string $type, string $title = 'Coastal Resilience Study'): string
{
    $body = match ($type) {
        'detailed_proposal' => 'DETAILED RESEARCH PROPOSAL Research Agenda Project Leader Proponent Agency Sustainable Development Goal',
        'work_plan' => 'MAJOR ACTIVITIES/WORK PLAN Total Duration Planned Start Planned End Expected Output',
        'line_item_budget' => 'LINE-ITEM BUDGET Particulars Amount (Php) Maintenance and Other Operating Expenses Capital Outlays',
    };

    return 'BatStateU-FO-RES-02 '.$body.' Project Title: '.$title.' Prepared by: Faculty Owner Checked and Verified by: Research Office';
}

function signingVerificationPayload(ProposalVersionFile $source, array $extra = []): array
{
    return [
        '_token' => csrf_token(),
        'purpose' => 'signed', 'source_file_id' => $source->id,
        'review_file' => UploadedFile::fake()->create('unrelated-filename.pdf', 100, 'application/pdf'),
        ...$extra,
    ];
}

test('local demo PDFs fill all three signing slots and record demo uploads', function (string $role, string $workspace) {
    $this->app->detectEnvironment(fn (): string => 'local');
    config(['proposal_signing.demo_mode' => true]);
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole($role);
    $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    foreach ([
        'gad_checklist' => ['purpose' => 'gad_assessment', 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed', 'gad_score' => 10],
        'initial_screening_form' => ['purpose' => 'evaluation', 'narrative_evaluation' => 'Recommended for endorsement.', 'recommended_action' => 'for_endorsement'],
    ] as $type => $assessment) {
        Storage::disk('local')->put('original/'.$type.'.pdf', '%PDF-1.4 original');
        $source = $this->version->files()->create([
            'document_type' => $type, 'file_path' => 'original/'.$type.'.pdf',
            'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put('assessed/'.$type.'.pdf', '%PDF-1.4 assessed');
        $this->version->files()->create([
            'document_type' => 'head_upload', 'position' => 100 + $source->id, 'source_version_file_id' => $source->id,
            'file_path' => 'assessed/'.$type.'.pdf', 'original_filename' => $type.'.pdf',
            'mime_type' => 'application/pdf', 'source_data' => ['target_document_type' => $type, ...$assessment],
        ]);
    }
    foreach (['detailed_proposal', 'work_plan', 'line_item_budget'] as $index => $type) {
        $source = $this->version->files()->where('document_type', $type)->sole();
        $filename = 'random-demo-'.($index + 1).'.pdf';
        $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source, [
            'review_file' => UploadedFile::fake()->createWithContent($filename, '%PDF-1.4 unrelated demonstration content'),
        ]))
            ->assertOk()->assertJsonPath('verification_status', 'demo_uploaded')
            ->assertJsonPath('filename', $filename)->assertJsonPath('complete', $index === 2);
        $signed = $this->version->files()->where('source_version_file_id', $source->id)->sole();
        expect($signed->source_data['signed_form_verification'])->toMatchArray([
            'status' => 'demo_uploaded', 'method' => 'demo', 'reason' => 'demo_mode',
            'document_type' => $type, 'checked_by' => $operator->id,
        ])
            ->and($signed->source_data['signed_form_verification']['checked_at'])->not->toBeEmpty()
            ->and(Storage::disk('local')->exists($signed->file_path))->toBeTrue();
    }
    Process::assertNothingRan();
    Http::assertNothingSent();
    expect(app(ProposalSignatureWorkflow::class)->isComplete($this->version->fresh()))->toBeTrue();
    $this->get(route('topics.show', $this->topic))->assertOk()
        ->assertSee('Any PDF is accepted for this demo.')->assertSee('data-signed-count="3"', false)
        ->assertDontSee('We check the official form and project title before saving.');
})->with([
    'office' => ['research_coordinator', 'research_office'],
    'secretary' => ['research_secretary', 'research_secretary'],
]);

test('form checks remain active without the local demo setting', function (string $environment, bool $enabled) {
    $this->app->detectEnvironment(fn (): string => $environment);
    config(['proposal_signing.demo_mode' => $enabled]);
    Process::fake(['*' => signingFormText('line_item_budget')]);
    $source = $this->version->files()->where('document_type', 'work_plan')->sole();
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertUnprocessable()->assertJsonValidationErrors('review_file');
    expect($this->version->files()->count())->toBe(3);
})->with([
    'local demo disabled' => ['local', false],
    'production' => ['production', true],
    'testing' => ['testing', true],
]);

test('demo uploads still require PDFs within the size limit', function (string $filename, string $mimeType, int $size) {
    $this->app->detectEnvironment(fn (): string => 'local');
    config(['proposal_signing.demo_mode' => true]);
    $source = $this->version->files()->where('document_type', 'work_plan')->sole();
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source, [
        'review_file' => UploadedFile::fake()->create($filename, $size, $mimeType),
    ]))->assertUnprocessable()->assertJsonValidationErrors('review_file');
    expect($this->version->files()->count())->toBe(3);
})->with([
    'text file' => ['notes.txt', 'text/plain', 1],
    'oversize PDF' => ['demo.pdf', 'application/pdf', 26 * 1024],
]);

test('demo uploads retain staff permissions and the final signing stage requirement', function () {
    $this->app->detectEnvironment(fn (): string => 'local');
    config(['proposal_signing.demo_mode' => true]);
    $source = $this->version->files()->where('document_type', 'work_plan')->sole();
    $this->actingAs($this->owner)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))->assertForbidden();
    $this->actingAs($this->staff)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW]);
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertForbidden();
    expect($this->version->files()->count())->toBe(3);
});

test('final signed forms are matched by contents and audited before storage', function (string $type, string $role, string $workspace) {
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole($role);
    $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    Process::fake(['*' => signingFormText($type)]);

    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertOk()->assertJsonPath('verification_status', 'matched');
    $signed = $source->version->files()->where('source_version_file_id', $source->id)->firstOrFail();
    expect($signed->source_data['signed_form_verification'])
        ->toMatchArray(['status' => 'matched', 'method' => 'pdf_text', 'document_type' => $type, 'project_title' => $this->topic->title, 'checked_by' => $operator->id]);
    expect($signed->source_data['signed_form_verification']['checked_at'])->not->toBeEmpty()
        ->and(Storage::disk('local')->exists($signed->file_path))->toBeTrue();
})->with(['detailed_proposal', 'work_plan', 'line_item_budget'])->with([
    'office' => ['research_coordinator', 'research_office'],
    'secretary' => ['research_secretary', 'research_secretary'],
]);

test('a wrong form cannot replace an existing signed copy even with manual confirmation', function () {
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    Storage::disk('local')->put('signed/previous.pdf', '%PDF-1.4 previous');
    $previous = $this->version->files()->create([
        'document_type' => 'head_upload', 'source_version_file_id' => $source->id,
        'file_path' => 'signed/previous.pdf', 'original_filename' => 'previous.pdf',
        'mime_type' => 'application/pdf',
        'source_data' => ['purpose' => 'signed', 'target_document_type' => 'work_plan'],
    ]);
    Process::fake(['*' => signingFormText('line_item_budget')]);
    $filesBefore = Storage::disk('local')->allFiles();

    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source, ['signed_form_manually_confirmed' => true]))
        ->assertUnprocessable()->assertJsonPath('requires_manual_review', false)->assertJsonValidationErrors('review_file');
    expect($previous->fresh()->superseded_at)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe($filesBefore)
        ->and($this->version->files()->count())->toBe(4);
});

test('a form for another project is rejected', function (string $type, string $title) {
    $source = $this->version->files()->where('document_type', $type)->firstOrFail();
    Process::fake(['*' => signingFormText($type, $title)]);
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertUnprocessable()->assertJsonPath('requires_manual_review', false)->assertJsonValidationErrors('review_file');
    expect($this->version->files()->count())->toBe(3);
})->with(['detailed_proposal', 'work_plan', 'line_item_budget'])->with(['Different Coastal Project', 'Coastal Resilience Study and Other Research']);

test('unreadable scans require an explicit manual check and record the checker', function () {
    Process::fake(['*' => '']);
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertUnprocessable()->assertJsonPath('requires_manual_review', true);
    expect($this->version->files()->count())->toBe(3);

    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source, ['signed_form_manually_confirmed' => true]))
        ->assertOk()->assertJsonPath('verification_status', 'manually_confirmed');
    $data = $this->version->files()->where('source_version_file_id', $source->id)->firstOrFail()->source_data['signed_form_verification'];
    expect($data)->toMatchArray(['status' => 'manually_confirmed', 'method' => 'manual', 'checked_by' => $this->staff->id, 'reason' => 'scan_could_not_be_read']);
});

test('malformed or password protected PDFs cannot bypass verification', function () {
    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'Unreadable PDF')]);
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source, ['signed_form_manually_confirmed' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('review_file');
    expect($this->version->files()->count())->toBe(3);
});

test('readable wet signature scans are identified using visible evidence', function (string $detectedTitle, string $detectedHeading, string $status) {
    config(['services.openrouter.key' => 'test-key']);
    Process::fake(function (PendingProcess $process) {
        if (str_contains(implode(' ', $process->command), '-png')) {
            File::put(end($process->command).'-1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB2sAAAAASUVORK5CYII='));
        }

        return Process::result(output: '');
    });
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'legible' => true, 'form_heading' => $detectedHeading,
        'reference_number' => 'Attachment A-BatStateU-FO-RES-02',
        'project_title' => $detectedTitle,
        'field_labels' => ['Total Duration (in months)', 'Planned Start', 'Planned End', 'Expected Output'],
    ])]]]])]);
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    $response = $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source));

    if ($status === 'matched') {
        $response->assertOk()->assertJsonPath('verification_status', 'matched');
        $verification = $this->version->files()->where('source_version_file_id', $source->id)->firstOrFail()->source_data['signed_form_verification'];
        expect($verification['method'])->toBe('scan_reader');
    } else {
        $response->assertUnprocessable()->assertJsonPath('requires_manual_review', false);
        expect($this->version->files()->count())->toBe(3);
    }

    Http::assertSent(fn (Request $request): bool => $request['messages'][1]['content'][1]['type'] === 'image_url'
        && ! str_contains(json_encode($request['messages']), $this->topic->title));
    expect(File::glob(storage_path('app/private/signing-verification/*')))->toBeEmpty();
})->with([
    'correct form' => ['Coastal Resilience Study', 'MAJOR ACTIVITIES/WORK PLAN', 'matched'],
    'wrong project' => ['Different Study', 'MAJOR ACTIVITIES/WORK PLAN', 'rejected'],
    'wrong form' => ['Coastal Resilience Study', 'LINE-ITEM BUDGET', 'rejected'],
]);

test('a scan reader outage or illegible response never silently verifies a file', function (bool $outage) {
    config(['services.openrouter.key' => 'test-key']);
    Process::fake(function (PendingProcess $process) {
        if (str_contains(implode(' ', $process->command), '-png')) {
            File::put(end($process->command).'-1.png', 'fake page image');
        }

        return Process::result(output: '');
    });
    Http::fake(['*' => $outage ? Http::response([], 429) : Http::response(['choices' => [['message' => ['content' => '{"legible":false,"project_title":null,"field_labels":[]}']]]])]);
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    $this->postJson(route('topics.head-uploads.store', $this->topic), signingVerificationPayload($source))
        ->assertUnprocessable()->assertJsonPath('requires_manual_review', true);
    expect($this->version->files()->count())->toBe(3)
        ->and(File::glob(storage_path('app/private/signing-verification/*')))->toBeEmpty();
})->with([true, false]);

test('the local PDF tools render scanned pages for the configured reader', function () {
    if (! is_file(config('research_assistant.document.pdftotext_binary')) || ! is_file(config('research_assistant.document.pdftoppm_binary'))) {
        $this->markTestSkipped('Configure absolute paths to the local PDF tools to run the rasterizer integration test.');
    }
    Process::swap(new Factory);
    config(['services.openrouter.key' => 'test-key']);
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'legible' => true, 'form_heading' => 'MAJOR ACTIVITIES/WORK PLAN',
        'reference_number' => 'Attachment A-BatStateU-FO-RES-02', 'project_title' => $this->topic->title,
        'field_labels' => ['Total Duration', 'Planned Start', 'Planned End', 'Expected Output'],
    ])]]]])]);
    $stream = 'q 300 0 0 300 0 0 cm /Scan Do Q';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Resources << /XObject << /Scan 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /ASCIIHexDecode /Length 7 >>\nstream\nFFFFFF>\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    $source = $this->version->files()->where('document_type', 'work_plan')->firstOrFail();
    $payload = signingVerificationPayload($source, ['review_file' => UploadedFile::fake()->createWithContent('scan.pdf', $pdf)]);
    $this->postJson(route('topics.head-uploads.store', $this->topic), $payload)
        ->assertOk()->assertJsonPath('verification_status', 'matched');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request['messages'][1]['content'][1]['image_url']['url'], 'data:image/png;base64,'));
    expect(File::glob(storage_path('app/private/signing-verification/*')))->toBeEmpty();
});
