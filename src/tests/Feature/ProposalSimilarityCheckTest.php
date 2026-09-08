<?php

use App\Models\ProposalSimilarityCheck;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('similarity checks keep version identity and restrict requests and private results', function () {
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Version-specific similarity', 'estimated_budget' => 10000, 'estimated_duration_months' => 12, 'status' => 'pending']);
    $version = $topic->versions()->create(['submitted_by' => $faculty->id, 'version_number' => 1, 'submission_type' => 'initial', 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => str_repeat('a', 64), 'title' => $topic->title, 'estimated_budget' => 10000, 'estimated_duration_months' => 12]);
    $file = $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'file_size' => 100]);
    Storage::disk('local')->put('proposal.pdf', '%PDF-1.4 original');
    $input = ['proposal_version_file_id' => $file->id];
    $this->actingAs($other)->post(route('similarity-checks.store', $topic), $input)->assertForbidden();
    $this->actingAs($faculty)->get(route('similarity-checks.index'))->assertOk()->assertSee($topic->title);
    $this->post(route('similarity-checks.store', $topic), $input)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('similarity-checks.store', $topic), $input)->assertRedirect();
    expect(ProposalSimilarityCheck::count())->toBe(1);
    $check = ProposalSimilarityCheck::firstOrFail();
    $this->patch(route('similarity-checks.update', $check), ['status' => 'in_progress'])->assertForbidden();
    $this->actingAs($head)->get(route('similarity-checks.index'))->assertOk()->assertSee('Download this proposal version');
    $this->patch(route('similarity-checks.update', $check), ['status' => 'in_progress'])->assertSessionHasNoErrors();
    $this->patch(route('similarity-checks.update', $check), ['status' => 'completed'])->assertSessionHasErrors('report');
    $this->patch(route('similarity-checks.update', $check), ['status' => 'completed', 'similarity_score' => 18.5, 'report' => UploadedFile::fake()->create('turnitin.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    expect($check->fresh()->status)->toBe('completed')->and($topic->fresh()->status)->toBe('pending');
    $this->actingAs($faculty)->get(route('similarity-checks.download', $check))->assertDownload('similarity-report-version-1.pdf');
    $this->actingAs($other)->get(route('similarity-checks.download', $check))->assertForbidden();
    $this->get(route('similarity-checks.index'))->assertDontSee($topic->title);
    $newVersion = $version->replicate();
    $newVersion->version_number = 2;
    $newVersion->save();
    $newFile = $file->replicate();
    $newFile->proposal_version_id = $newVersion->id;
    $newFile->save();
    $this->actingAs($faculty)->post(route('similarity-checks.store', $topic), $input)->assertStatus(409);
    $this->post(route('similarity-checks.store', $topic), ['proposal_version_file_id' => $newFile->id])->assertRedirect();
    expect(ProposalSimilarityCheck::count())->toBe(2)->and($check->fresh()->proposal_version_file_id)->toBe($file->id);
});
