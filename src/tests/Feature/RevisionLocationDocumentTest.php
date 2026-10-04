<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Revised study', 'status' => 'revision_requested']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'topic_id' => $this->topic->id, 'project_title' => 'Revised study']);
    Storage::fake('local');
    Storage::disk('local')->put('revision/current.pdf', '%PDF-1.7 revised text');
    $this->document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0,
        'file_path' => 'revision/current.pdf', 'original_filename' => 'current.pdf', 'mime_type' => 'application/pdf',
    ]);
    $this->url = route('faculty.proposal-drafts.revision-files.show', [$this->draft, $this->document]);
});

test('faculty can read the exact staged revised PDF for automatic response locations', function () {
    $this->actingAs($this->faculty)->get($this->url)
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')->assertContent('%PDF-1.7 revised text');
});

test('revised PDF lookup rejects other faculty and documents belonging to another draft', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $this->actingAs($other)->get($this->url)->assertForbidden();
    $second = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Other draft']);
    $otherDocument = $second->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'revision/current.pdf']);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision-files.show', [$this->draft, $otherDocument]))->assertNotFound();
});

test('revised PDF lookup rejects missing files and closed revision windows', function () {
    Storage::disk('local')->delete('revision/current.pdf');
    $this->actingAs($this->faculty)->get($this->url)->assertNotFound();
    Storage::disk('local')->put('revision/current.pdf', '%PDF-1.7 revised text');
    $this->topic->update(['status' => 'approved']);
    $this->get($this->url)->assertForbidden();
});

test('revised Word files use the same PDF converter as submission and report conversion failures', function () {
    Storage::disk('local')->put('revision/current.docx', 'exact revised Word content');
    $this->document->update(['file_path' => 'revision/current.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->with('exact revised Word content')->andReturn('%PDF-1.7 exact converted content');
    $this->actingAs($this->faculty)->get($this->url)->assertOk()->assertContent('%PDF-1.7 exact converted content');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->andThrow(new RuntimeException('Converter unavailable'));
    $this->getJson($this->url)->assertStatus(503)->assertJsonPath('message', 'The revised Word file could not be read. Try a PDF replacement.');
});
