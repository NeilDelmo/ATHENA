<?php

use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('archived draft history loads summaries without saved document bodies', function () {
    $this->withoutVite();
    Storage::fake('local');
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $faculty = User::factory()->create(['name' => 'Archived paper author']);
    $faculty->assignRole('faculty');
    $topic = TopicProposal::create([
        'user_id' => $faculty->id, 'research_manual_id' => null,
        'title' => 'Preserved proposal', 'status' => 'pending',
    ]);
    $body = str_repeat('PRIVATE_HISTORICAL_DOCUMENT_BODY ', 10000);
    $versions = collect();
    $path = 'proposal-packages/'.$faculty->id.'/archived-work-plan.pdf';
    Storage::disk('local')->put($path, '%PDF-preserved');
    foreach (range(1, 21) as $number) {
        $versions->push(ProposalDraftDocumentVersion::create([
            'topic_id' => $topic->id, 'created_by' => $faculty->id,
            'document_type' => in_array($number, [1, 21], true)
                ? ProposalVersionFile::TYPE_WORK_PLAN : ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'version_number' => $number, 'is_current' => false,
            'action' => $number === 21 ? ProposalDraftDocumentVersion::ACTION_RESTORED : ProposalDraftDocumentVersion::ACTION_SUBMITTED,
            'change_summary' => $number === 20 ? 'Completed Detailed Research Proposal' : 'Archived checkpoint '.$number,
            'change_note' => $number === 21 ? 'Preserved restoration details' : null,
            'changes' => [['label' => 'Objective', 'before' => 'Original objective', 'after' => 'Corrected objective']],
            'restored_from_version_id' => $number === 21 ? $versions->first()->id : null,
            'source_data' => ['rationale' => $body],
            'file_path' => $number === 21 ? $path : null,
            'original_filename' => $number === 21 ? 'archived-work-plan.pdf' : null,
            'mime_type' => $number === 21 ? 'application/pdf' : null,
            'file_size' => $number === 21 ? 2048 : null,
            'completed_at' => $number === 21 ? now() : null,
        ]));
    }

    $response = $this->actingAs($faculty)->get(route('topics.draft-history.index', $topic));
    $response->assertOk()
        ->assertSee('Archived checkpoint 21')
        ->assertSee('Saved Detailed Research Proposal as a draft.')
        ->assertSee('Preserved restoration details')
        ->assertSee('Restored from an earlier saved version.')
        ->assertSee('Original objective')
        ->assertSee('Corrected objective')
        ->assertSee('archived-work-plan.pdf')
        ->assertSee($faculty->name)
        ->assertDontSee('PRIVATE_HISTORICAL_DOCUMENT_BODY');
    $history = $response->viewData('versions');
    $hydratedBytes = $history->sum(fn ($version): int => strlen(json_encode($version->getAttributes(), JSON_THROW_ON_ERROR)));
    if (getenv('ATHENA_OTHER_PAGE_BENCHMARK') === '1') {
        fwrite(STDERR, sprintf("Archived draft history: %d models, %d hydrated attribute bytes\n", $history->count(), $hydratedBytes));
    }
    expect($history->total())->toBe(21)
        ->and($history->getCollection()->modelKeys())->toBe($versions->reverse()->take(20)->pluck('id')->all())
        ->and($history->first()->creator->id)->toBe($faculty->id)
        ->and($history->first()->restoredFrom->id)->toBe($versions->first()->id);
    expect($hydratedBytes)->toBeLessThan(20000);
    foreach ($history as $version) {
        expect($version->getAttributes())->not->toHaveKey('source_data');
    }

    $this->get(route('topics.draft-history.index', [$topic, 'page' => 2]))
        ->assertOk()
        ->assertViewHas('versions', fn ($page): bool => $page->getCollection()->modelKeys() === [$versions->first()->id]);
    $this->get(route('topics.draft-history.index', [$topic, 'paper' => 'work-plan']))
        ->assertOk()
        ->assertViewHas('versions', fn ($page): bool => $page->getCollection()->modelKeys() === [$versions->last()->id, $versions->first()->id]);
    $this->get(route('topics.draft-history.download', [$topic, $versions->last()]))
        ->assertDownload('archived-work-plan.pdf');
    expect($versions->last()->fresh()->source_data['rationale'])->toBe($body);

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('topics.draft-history.index', $topic))->assertOk();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    $this->actingAs($outsider)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('topics.draft-history.index', $topic))->assertForbidden();
    $this->get(route('topics.draft-history.download', [$topic, $versions->last()]))->assertForbidden();

});
