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

test('research head can attach a signed copy of a required paper to the latest package', function () {
    $response = $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'note' => 'Signed by external co-evaluator on 2026-07-21.',
        ]);

    $response->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHas('success', 'Signed copy attached to the proposal package.');

    $headUpload = $this->version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();

    expect($headUpload->uploaded_by)->toBe($this->head->id)
        ->and($headUpload->original_filename)->toBe('signed-work-plan.pdf')
        ->and($headUpload->source_data['target_document_type'])->toBe(ProposalVersionFile::TYPE_WORK_PLAN)
        ->and($headUpload->source_data['note'])->toBe('Signed by external co-evaluator on 2026-07-21.')
        ->and(Storage::disk('local')->exists($headUpload->file_path))->toBeTrue();

    expect($this->topic->reviews()->where('decision', 'head_upload')->count())->toBe(1);
});

test('signed copy can be attached even after the proposal is approved', function () {
    $this->topic->update([
        'status' => 'approved',
        'project_status' => 'ongoing',
    ]);

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
            'signed_file' => UploadedFile::fake()->create('signed-gad-checklist.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHas('success', 'Signed copy attached to the proposal package.');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(1);
});

test('faculty cannot attach a signed copy through the research head upload endpoint', function () {
    $this->actingAs($this->faculty)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('rogue.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload requires a valid target document type among the seven required papers', function () {
    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => 'totally_unknown_type',
            'signed_file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['target_document_type'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('upload rejects unsupported file types and oversize files', function () {
    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('signed.txt', 100, 'text/plain'),
        ])
        ->assertSessionHasErrors(['signed_file'], null, 'headUpload');

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['signed_file'], null, 'headUpload');

    expect($this->version->files()->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)->count())->toBe(0);
});

test('the proposal workspace surfaces signed copies alongside the faculty package', function () {
    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'note' => 'Co-signed by the RDES Head.',
        ]);

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertOk()
        ->assertSee('Signed by Research Head')
        ->assertSee('signed-work-plan.pdf')
        ->assertSee('Co-signed by the RDES Head.')
        ->assertSee('Attach signed copy');
});

test('the head action timeline records head upload events', function () {
    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $this->topic), [
            'target_document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'signed_file' => UploadedFile::fake()->create('signed-work-plan.pdf', 100, 'application/pdf'),
            'note' => 'Co-signed on 2026-07-21.',
        ]);

    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic));

    $response->assertSee('head upload')
        ->assertSee('Co-signed on 2026-07-21.');
});
