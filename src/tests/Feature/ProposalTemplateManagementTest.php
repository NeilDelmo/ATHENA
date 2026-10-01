<?php

use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'research_head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Storage::fake('local');

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
});

test('template administration routes have been removed', function () {
    foreach (['index', 'store', 'update', 'status'] as $action) {
        expect(Route::has('research_head.proposal-templates.'.$action))->toBeFalse();
    }
    $this->actingAs($this->head)->get('/research-head/proposal-templates')->assertNotFound();
    $this->post('/research-head/proposal-templates', [])->assertNotFound();
    $this->put('/research-head/proposal-templates/example', [])->assertNotFound();
    $this->patch('/research-head/proposal-templates/example/status', [])->assertNotFound();
    $this->actingAs($this->faculty)->get('/research-head/proposal-templates')->assertNotFound();
});

test('official template downloads still work and archived files remain restricted', function () {
    $template = ProposalTemplate::create([
        'slug' => 'test-official-form',
        'name' => 'Official Proposal Form',
        'workflow_stage' => ProposalTemplate::STAGE_INITIAL_SUBMISSION,
        'file_path' => 'proposals/templates/official-form.pdf',
        'original_filename' => 'official-form.pdf',
        'is_active' => true,
        'uploaded_by' => $this->head->id,
    ]);
    Storage::disk('local')->put($template->file_path, '%PDF-1.4 test form');
    $this->actingAs($this->faculty)->get(route('proposal-templates.download', $template))
        ->assertDownload('official-form.pdf');

    $template->update(['is_active' => false]);
    $this->get(route('proposal-templates.download', $template))->assertNotFound();
    $this->actingAs($this->head)->get(route('proposal-templates.download', $template))
        ->assertDownload('official-form.pdf');

    Storage::disk('local')->delete($template->file_path);
    $this->get(route('proposal-templates.download', $template))->assertNotFound();
});
