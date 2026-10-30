<?php

use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\DetailedProposalDocumentService;
use App\Support\DetailedProposalData;
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

test('removing RRL citations and optional wording persists after reopening without deleting the saved source', function (string $remainingText) {
    $document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => [
            'related_literature' => '<p>Researcher wording.<span data-proposal-citation="'.$this->link['id'].'"> [1]</span></p>',
            'references' => '<p data-proposal-managed-reference="'.$this->link['id'].'">[1] '.$this->link['reference'].'</p>',
            'literature_citations' => json_encode([[
                'source_link_id' => $this->link['id'], 'field' => 'related_literature', 'selected_text' => 'Researcher wording.',
            ]], JSON_THROW_ON_ERROR),
        ],
    ]);

    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'related_literature' => $remainingText, 'references' => '', 'literature_citations' => '[]',
    ])->assertOk();

    $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertViewHas('sourceData', fn (array $sourceData): bool => $sourceData['related_literature'] === $remainingText
            && $sourceData['references'] === ''
            && json_decode($sourceData['literature_citations'], true, flags: JSON_THROW_ON_ERROR) === []);

    expect($document->fresh()->source_data['related_literature'])->toBe($remainingText)
        ->and($this->draft->literatureSources()->find($this->link['id']))->not->toBeNull();
    $this->assertModelExists(LiteratureSource::findOrFail($this->link['literature_source_id']));
})->with([
    'keep the paragraph' => '<p>Researcher wording.</p>',
    'delete the paragraph' => '',
]);

test('discarding a saved RRL draft clears its generation details while keeping inserted text and the source', function () {
    $link = $this->draft->literatureSources()->findOrFail($this->link['id']);
    $link->update([
        'rrl_note' => 'Reviewed research notes that are no longer wanted.',
        'rrl_draft_status' => 'confirmed', 'rrl_evidence_basis' => 'full_text',
        'rrl_word_count' => 9, 'rrl_generated_at' => now(),
    ]);
    $sourceData = ['related_literature' => '<p>Already inserted researcher wording.</p>'];
    $document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0, 'source_data' => $sourceData,
    ]);

    $this->deleteJson(route('faculty.proposal-drafts.literature-drafts.destroy', [$this->draft, $link]))
        ->assertOk()
        ->assertJsonPath('source.rrl_note', '')
        ->assertJsonPath('source.rrl_draft_status', 'none')
        ->assertJsonPath('source.rrl_evidence_basis', null)
        ->assertJsonPath('source.rrl_word_count', null)
        ->assertJsonPath('source.rrl_generated_at', null);

    expect($link->fresh()->rrl_note)->toBeNull()
        ->and($document->fresh()->source_data)->toBe($sourceData);
    $this->assertModelExists($link);
    $this->assertModelExists(LiteratureSource::findOrFail($this->link['literature_source_id']));
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

test('managed references survive autosave reopening and Word export', function () {
    $reference = '[1] A. Reader, "Reading evidence in community monitoring," 2025.';
    $html = '<p data-proposal-managed-reference="'.$this->link['id'].'">'.htmlspecialchars($reference, ENT_QUOTES, 'UTF-8').'</p>';

    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => '1',
        'references' => $html,
    ])->assertOk();
    $document = $this->draft->documents()->where('document_type', config('proposal_papers.detailed-proposal.document_type'))->sole();

    expect($document->source_data['references'])->toBe($html);
    $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertViewHas('sourceData', fn (array $sourceData): bool => $sourceData['references'] === $html);

    $proposal = DetailedProposalData::fromValidated([
        ...$document->source_data,
        'project_title' => $this->draft->project_title,
        'project_leader' => $this->draft->project_leader,
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'managed-reference-export-');
    file_put_contents($temporaryPath, app(DetailedProposalDocumentService::class)->generate($proposal));
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $xml = $archive->getFromName('word/document.xml');
        expect(html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8'))
            ->toContain($reference)
            ->not->toContain('data-proposal-managed-reference');
    } finally {
        $archive->close();
        unlink($temporaryPath);
    }
});
