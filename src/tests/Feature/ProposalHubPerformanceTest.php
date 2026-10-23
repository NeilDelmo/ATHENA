<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

test('proposal hub recent activity loads summaries without historical document bodies', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Private proposal hub']);
    $body = str_repeat('PRIVATE_HISTORICAL_DOCUMENT_BODY ', 10000);
    $versions = collect();
    foreach (range(1, 6) as $number) {
        $versions->push($draft->documentVersions()->create([
            'created_by' => $faculty->id,
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'version_number' => $number,
            'change_summary' => match ($number) {
                5 => null,
                6 => 'Completed Detailed Research Proposal',
                default => 'Saved proposal checkpoint '.$number,
            },
            'source_data' => ['rationale' => $body],
            'changes' => ['rationale' => ['before' => $body, 'after' => $body]],
            'created_at' => now()->subMinutes(7 - $number),
        ]));
    }

    $response = $this->actingAs($faculty)->get(route('faculty.proposal-drafts.show', $draft));
    $response->assertOk()
        ->assertSee('Saved proposal checkpoint 2')
        ->assertSee('Saved Detailed Research Proposal')
        ->assertSee('Saved Detailed Research Proposal as a draft.')
        ->assertSee($faculty->name)
        ->assertDontSee('Saved proposal checkpoint 1')
        ->assertDontSee('PRIVATE_HISTORICAL_DOCUMENT_BODY');
    $activity = $response->viewData('recentActivity');
    $hydratedBytes = $activity->sum(fn ($version): int => strlen(json_encode($version->getAttributes(), JSON_THROW_ON_ERROR)));
    if (getenv('ATHENA_OTHER_PAGE_BENCHMARK') === '1') {
        fwrite(STDERR, sprintf("Proposal hub recent activity: %d models, %d hydrated attribute bytes\n", $activity->count(), $hydratedBytes));
    }

    $response->assertViewHas('recentActivity', fn (Collection $activity): bool => $activity->modelKeys() === $versions->reverse()->take(5)->pluck('id')->all());
    expect($hydratedBytes)->toBeLessThan(5000);
    foreach ($activity as $version) {
        expect($version->getAttributes())->not->toHaveKeys(['source_data', 'changes']);
    }

    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    $this->actingAs($outsider)->get(route('faculty.proposal-drafts.show', $draft))->assertForbidden();
});
