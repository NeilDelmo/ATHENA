<?php

use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    Storage::fake('local');
    $this->withoutVite();

    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');

    $this->faculty = User::factory()->create(['name' => 'Lead Faculty']);
    $this->faculty->assignRole('faculty');

    $this->call = ResearchCall::create([
        'title' => 'Open Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);

    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Coastal Habitat Restoration',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'status' => 'pending',
    ]);

    $this->version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/coastal-habitat-restoration.pdf',
        'original_filename' => 'coastal-habitat-restoration.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'checksum' => str_repeat('a', 64),
        'title' => $this->topic->title,
        'estimated_budget' => $this->topic->estimated_budget,
        'estimated_duration_months' => $this->topic->estimated_duration_months,
    ]);

    foreach ([
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        ProposalVersionFile::TYPE_CURRICULUM_VITAE,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
    ] as $position => $documentType) {
        $path = "packages/{$documentType}.pdf";
        Storage::disk('local')->put($path, $documentType);

        $this->version->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => $path,
            'original_filename' => "{$documentType}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => strlen($documentType),
            'checksum' => hash('sha256', $documentType),
            'is_carried_forward' => false,
        ]);
    }
});

test('research head can attach reviewed files to exact faculty submissions', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $response = $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Annotated for the faculty revision.',
        ]);

    $response->assertRedirect(route('topics.head-uploads.index', $this->topic))
        ->assertSessionHas('success', 'Research Head file attached to the faculty submission.');

    $headUpload = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();

    expect($headUpload->uploaded_by)->toBe($this->head->id)
        ->and($headUpload->source_version_file_id)->toBe($workPlan->id)
        ->and($headUpload->original_filename)->toBe('reviewed-work-plan.pdf')
        ->and($headUpload->source_data['target_document_type'])->toBe(ProposalVersionFile::TYPE_WORK_PLAN)
        ->and($headUpload->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION)
        ->and($headUpload->source_data['note'])->toBe('Annotated for the faculty revision.')
        ->and(Storage::disk('local')->exists($headUpload->file_path))->toBeTrue();

    expect($this->topic->reviews()->where('decision', 'head_upload')->count())->toBe(1);
});

test('signed copy can be attached even after the proposal is approved', function () {
    $this->topic->update([
        'status' => 'approved',
        'project_status' => 'ongoing',
    ]);

    $gadChecklist = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $gadChecklist->id,
            'review_file' => UploadedFile::fake()->create('signed-gad-checklist.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.head-uploads.index', $this->topic))
        ->assertSessionHas('success', 'Research Head file attached to the faculty submission.');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(1);
});

test('research head can upload a standalone supplemental paper after faculty turn in', function () {
    $response = $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'review_file' => UploadedFile::fake()->create('regional-endorsement.pdf', 120, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
            'document_title' => 'Regional Endorsement Memorandum',
            'issuing_office' => 'Office of the Regional Director',
            'note' => 'Received through the Research Head for the proposal record.',
        ]);

    $response->assertRedirect(route('topics.head-uploads.index', $this->topic))
        ->assertSessionHas('success', 'Supplemental paper uploaded by the Research Head.');

    $supplementalPaper = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();

    expect($supplementalPaper->uploaded_by)->toBe($this->head->id)
        ->and($supplementalPaper->source_version_file_id)->toBeNull()
        ->and($supplementalPaper->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL)
        ->and($supplementalPaper->source_data['document_title'])->toBe('Regional Endorsement Memorandum')
        ->and($supplementalPaper->source_data['issuing_office'])->toBe('Office of the Regional Director')
        ->and(Storage::disk('local')->exists($supplementalPaper->file_path))->toBeTrue();

    $this->actingAs($this->head)
        ->get(route('topics.head-uploads.index', $this->topic))
        ->assertOk()
        ->assertSee('Administrative and supplemental papers')
        ->assertSee('Regional Endorsement Memorandum')
        ->assertSee('Office of the Regional Director');
});

test('faculty cannot attach a signed copy through the research head upload endpoint', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->faculty)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('rogue.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertForbidden();

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload requires an exact faculty file from the latest version', function () {
    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => 999999,
            'review_file' => UploadedFile::fake()->create('reviewed.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['source_file_id'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload rejects unsupported file types and oversize files', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed.txt', 100, 'text/plain'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    $this->actingAs($this->head)
        ->from(route('topics.head-uploads.index', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
        ])
        ->assertSessionHasErrors(['review_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('the upload workspace mirrors the faculty package and surfaces research head copies', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('reviewed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
            'note' => 'Use these annotations for the next revision.',
        ]);

    $response = $this->actingAs($this->head)->get(route('topics.head-uploads.index', $this->topic));

    $response->assertOk()
        ->assertSee('Review and Upload Files')
        ->assertSee('Faculty-submitted files')
        ->assertSee('Faculty original')
        ->assertSee('reviewed-work-plan.pdf')
        ->assertSee('For revision')
        ->assertSee('Use these annotations for the next revision.')
        ->assertSee('Upload copy');
});

test('the head action timeline records head upload events', function () {
    $workPlan = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'source_file_id' => $workPlan->id,
            'review_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            'note' => 'Co-signed on 2026-07-21.',
        ]);

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertSee('head upload')
        ->assertSee('Co-signed on 2026-07-21.');
});
