<?php

use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

test('only a research head can manage proposal templates', function () {
    $this->withoutVite();

    $this->actingAs($this->head)
        ->get(route('research_head.proposal-templates.index'))
        ->assertOk()
        ->assertSee('Proposal Template Administration');

    $this->actingAs($this->faculty)
        ->get(route('research_head.proposal-templates.index'))
        ->assertForbidden();
});

test('a research head can upload replace and archive a proposal template', function () {
    $this->withoutVite();

    $this->actingAs($this->head)
        ->post(route('research_head.proposal-templates.store'), [
            'name' => 'Ethics Clearance Guide',
            'description' => 'Official ethics preparation form.',
            'instructions' => 'Complete this when human participants are involved.',
            'revision_label' => 'Revision 01',
            'workflow_stage' => ProposalTemplate::STAGE_INITIAL_SUBMISSION,
            'document' => UploadedFile::fake()->create('ethics-guide.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertRedirect();

    $template = ProposalTemplate::where('slug', 'ethics-clearance-guide')->firstOrFail();
    $originalPath = $template->file_path;

    Storage::disk('local')->assertExists($originalPath);
    expect($template->is_active)->toBeTrue()
        ->and($template->file_path)->toStartWith('proposals/templates/')
        ->and($template->instructions)->toContain('human participants');

    $this->actingAs($this->faculty)
        ->get(route('faculty.topics.create'))
        ->assertOk()
        ->assertSee('Ethics Clearance Guide');

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', $template))
        ->assertDownload('ethics-guide.docx');

    $this->actingAs($this->head)
        ->put(route('research_head.proposal-templates.update', $template), [
            'name' => 'Ethics Clearance Guide',
            'description' => 'Updated ethics preparation form.',
            'instructions' => 'Use the current institutional ethics process.',
            'revision_label' => 'Revision 02',
            'workflow_stage' => ProposalTemplate::STAGE_INITIAL_SCREENING,
            'document' => UploadedFile::fake()->create('ethics-guide-v2.pdf', 60, 'application/pdf'),
        ])
        ->assertRedirect();

    $template->refresh();
    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists($template->file_path);
    expect($template->revision_label)->toBe('Revision 02')
        ->and($template->workflow_stage)->toBe(ProposalTemplate::STAGE_INITIAL_SCREENING);

    $this->actingAs($this->head)
        ->patch(route('research_head.proposal-templates.status', $template), ['is_active' => false])
        ->assertRedirect();

    expect($template->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', $template))
        ->assertNotFound();

    $this->actingAs($this->head)
        ->get(route('proposal-templates.download', $template))
        ->assertDownload('ethics-guide-v2.pdf');
});
