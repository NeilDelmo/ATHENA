<?php

use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use App\Support\ProposalRichText;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Source evidence call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open',
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Community monitoring', 'project_leader' => $this->faculty->name,
        'duration_months' => 12, 'planned_start' => now()->addMonth()->toDateString(),
        'planned_end' => now()->addMonths(13)->toDateString(),
    ]);
    $this->actingAs($this->faculty);
    $sourceId = $this->postJson(route('research-support.literature-library.store'), [
        'title' => 'Reading evidence in community monitoring', 'authors' => 'A. Reader',
        'year' => 2025, 'doi' => '10.1234/reading-evidence',
        'url' => 'https://doi.org/10.1234/reading-evidence', 'source' => 'Manual entry', 'type' => 'article',
    ])->assertCreated()->json('source.id');
    $this->link = $this->postJson(route('faculty.proposal-drafts.literature-sources.store', [
        $this->draft, LiteratureSource::findOrFail($sourceId),
    ]))->assertCreated()->json('source');
    $this->withoutVite();
});

test('caret citations survive autosave with their page locator', function (string $field) {
    $html = '<p>Researcher wording.<span data-proposal-citation="'.$this->link['id'].'" data-proposal-locator="p. 6"> [1, p. 6]</span></p>';
    $payload = [
        'document_version' => 0, 'draft_version' => $this->draft->lock_version, 'save_as_draft' => '1',
        'literature_citations' => json_encode([[
            'id' => 'caret-citation', 'source_link_id' => $this->link['id'],
            'literature_source_id' => 999999, 'field' => $field, 'selected_text' => '', 'locator' => 'p. 6',
        ]], JSON_THROW_ON_ERROR),
    ];
    data_set($payload, $field, $html);
    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)->assertOk();
    $document = $this->draft->documents()->where('document_type', config('proposal_papers.detailed-proposal.document_type'))->sole();
    $citations = json_decode($document->source_data['literature_citations'], true, flags: JSON_THROW_ON_ERROR);

    expect($citations)->toHaveCount(1)
        ->and($citations[0])->toMatchArray([
            'selected_text' => '', 'field' => $field, 'locator' => 'p. 6',
            'literature_source_id' => $this->link['literature_source_id'],
        ])
        ->and(data_get($document->source_data, $field))->toContain('data-proposal-locator="p. 6"', '[1, p. 6]');
})->with(['related_literature', 'methodology.data_analysis']);

test('empty citation records require a matching marker in the same section', function () {
    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => '1',
        'rationale' => '<p>Existing text.<span data-proposal-citation="'.$this->link['id'].'"> [1]</span></p>',
        'related_literature' => '<p>There is no citation here.</p>',
        'literature_citations' => json_encode([
            ['source_link_id' => $this->link['id'], 'field' => 'related_literature', 'selected_text' => ''],
            ['source_link_id' => 999999, 'field' => 'rationale', 'selected_text' => 'Existing text.'],
        ], JSON_THROW_ON_ERROR),
    ])->assertOk();
    $document = $this->draft->documents()->where('document_type', config('proposal_papers.detailed-proposal.document_type'))->sole();

    expect(json_decode($document->source_data['literature_citations'], true, flags: JSON_THROW_ON_ERROR))->toBe([]);
});

test('citation locators remain plain text in saved markup and exported narrative runs', function () {
    $richText = app(ProposalRichText::class);
    $html = '<p>Supported wording.<span data-proposal-citation="42" data-proposal-locator="p. 6 &quot;quoted&quot;" onclick="alert(1)"> [1, p. 6]</span></p>';
    $sanitized = $richText->sanitize($html);

    expect($sanitized)->toContain('data-proposal-locator="p. 6 &quot;quoted&quot;"')
        ->not->toContain('onclick')
        ->and(collect($richText->blocks($sanitized))->flatMap(fn (array $block): array => $block['runs'])->pluck('text')->implode(''))
        ->toContain('Supported wording.', '[1, p. 6]');
});
