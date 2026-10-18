<?php

use App\Actions\RecordProposalDraftDocumentVersion;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\DetailedProposalDocumentService;
use App\Support\DetailedProposalData;
use Database\Seeders\VerifiedFloodLiteratureSeeder;
use Illuminate\Support\Arr;

beforeEach(function () {
    $owner = User::factory()->create();
    $call = ResearchCall::create([
        'title' => 'Sample call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open',
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $owner->id, 'research_call_id' => $call->id,
        'project_title' => 'IoT-Based Flood Monitoring and Early Warning System for Coastal Barangays',
        'project_leader' => $owner->name, 'duration_months' => 6,
        'planned_start' => '2026-10-17', 'planned_end' => '2027-04-16',
        'signatory_selections' => ['approved_by_name' => ['name' => 'Dr. Example Approver']],
    ]);
    $this->document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0, 'lock_version' => 1,
        'completed_at' => now(),
        'source_data' => [
            'project_title' => $this->draft->project_title, 'project_leader' => $owner->name,
            'related_literature' => 'Recent studies associate well-designed interventions in disaster preparedness and environmental monitoring with measurable efficiency, quality, and satisfaction gains. Adoption depends on usability, trust, accessibility, and the readiness of the people who will use the system.',
            'references' => 'Reyes, P. (2025). Low-cost sensor networks for flood-prone communities. Philippine Engineering Journal, 47(1), 12-29. Santos, M. (2025). Applied research methods for community-centered technology. Philippine Journal of Science, 154(2), 101-115.',
            'methodology' => ['research_design' => 'Keep the existing planned methods.'],
            'approved_by_name' => 'Dr. Example Approver',
        ],
    ]);
    $this->budget = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0, 'lock_version' => 3,
        'source_data' => ['project_total' => 25100, 'certified_by' => 'Dr. Example Approver'],
    ]);
    $this->draft->refresh();
    $this->document->refresh();
    $this->budget->refresh();
});

test('verified flood literature connects real sources and preserves project facts and recovery history', function () {
    $draftBefore = $this->draft->getAttributes();
    $sourceBefore = $this->document->source_data;
    $budgetBefore = $this->budget->getAttributes();

    $this->seed(VerifiedFloodLiteratureSeeder::class);
    $document = $this->document->fresh();
    $data = $document->source_data;
    $links = $this->draft->literatureSources()->orderBy('id')->get();
    $citations = json_decode($data['literature_citations'], true, flags: JSON_THROW_ON_ERROR);

    expect($links)->toHaveCount(3)
        ->and(LiteratureSource::count())->toBe(3)
        ->and($citations)->toHaveCount(3)
        ->and($links->pluck('doi')->all())->toBe([
            '10.3390/geosciences13030065', '10.1016/j.wroa.2024.100298', '10.3390/s22062124',
        ])
        ->and(Arr::except($data, ['related_literature', 'references', 'literature_citations']))
        ->toEqual(Arr::except($sourceBefore, ['related_literature', 'references', 'literature_citations']))
        ->and($this->draft->fresh()->getAttributes())->toBe($draftBefore)
        ->and(Arr::except($this->budget->fresh()->getAttributes(), ['updated_at']))->toBe(Arr::except($budgetBefore, ['updated_at']))
        ->and($document->lock_version)->toBeGreaterThan(1)
        ->and($document->completed_at)->toEqual($this->document->completed_at)
        ->and($document->versions()->count())->toBe(2)
        ->and($document->versions()->reorder('version_number')->first()->source_data)->toEqual($sourceBefore)
        ->and($data['related_literature'])->toContain('Tennessee', 'pressure', 'local', 'community')
        ->not->toContain('Recent studies associate', '99%')
        ->and($data['references'])->not->toContain('Reyes, P.', 'Santos, M.');

    foreach ($links as $index => $link) {
        expect($link->literature_source_id)->not->toBeNull()
            ->and($data['related_literature'])->toContain('data-proposal-citation="'.$link->id.'"', '['.($index + 1).']')
            ->and($data['references'])->toContain('data-proposal-managed-reference="'.$link->id.'"', $link->doi)
            ->and($citations[$index])->toMatchArray([
                'source_link_id' => $link->id, 'literature_source_id' => $link->literature_source_id,
                'field' => 'related_literature',
            ]);
    }

    $attributesAfter = $document->getAttributes();
    $this->seed(VerifiedFloodLiteratureSeeder::class);
    expect($document->fresh()->getAttributes())->toBe($attributesAfter)
        ->and($document->versions()->count())->toBe(2)
        ->and($this->draft->literatureSources()->count())->toBe(3)
        ->and(LiteratureSource::count())->toBe(3);

    $temporaryPath = tempnam(sys_get_temp_dir(), 'verified-flood-export-');
    file_put_contents($temporaryPath, app(DetailedProposalDocumentService::class)->generate(DetailedProposalData::fromValidated($data)));
    $archive = new ZipArchive;
    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $xml = html_entity_decode($archive->getFromName('word/document.xml'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        expect($xml)->toContain('10.3390/geosciences13030065', '10.1016/j.wroa.2024.100298', '10.3390/s22062124', '[1]', '[2]', '[3]')
            ->not->toContain('data-proposal-citation', 'data-proposal-managed-reference');
    } finally {
        $archive->close();
        unlink($temporaryPath);
    }
});

test('verified flood literature skips edited or unrelated samples without saving sources', function (string $change) {
    if ($change === 'title') {
        $this->draft->update(['project_title' => 'A different research project']);
    } elseif ($change === 'status') {
        $this->draft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);
    } else {
        $data = $this->document->source_data;
        $data[$change] = 'The researcher has edited this field.';
        $this->document->update(['source_data' => $data]);
    }
    $before = $this->document->fresh()->getAttributes();

    $this->seed(VerifiedFloodLiteratureSeeder::class);

    expect($this->document->fresh()->getAttributes())->toBe($before)
        ->and($this->draft->literatureSources()->count())->toBe(0)
        ->and(LiteratureSource::count())->toBe(0)
        ->and($this->document->versions()->count())->toBe(0);
})->with(['related_literature', 'references', 'literature_citations', 'title', 'status']);

test('verified sample literature cannot run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app()->call([app(VerifiedFloodLiteratureSeeder::class), 'run']))->toThrow(RuntimeException::class)
            ->and(LiteratureSource::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('verified literature reuses matching shared sources without changing their metadata', function () {
    $source = LiteratureSource::create([
        'fingerprint' => LiteratureSource::fingerprintFor('10.3390/geosciences13030065', '', 2023),
        'added_by' => User::factory()->create()->id,
        'title' => 'Low-Cost Real-Time Water Level Monitoring Network for Falling Water River Watershed: A Case Study',
        'authors' => 'A. Kalyanapu; C. Owusu; T. Wright; T. Datta',
        'publication_year' => 2023, 'publication_date' => '2023-02-26',
        'venue' => 'Geosciences', 'volume' => '13', 'issue' => '3', 'pages' => 'Art. no. 65', 'publisher' => 'MDPI',
        'doi' => '10.3390/geosciences13030065', 'url' => 'https://www.mdpi.com/2076-3263/13/3/65',
        'provider' => 'Existing shared library',
    ])->refresh();
    $before = $source->getAttributes();

    $this->seed(VerifiedFloodLiteratureSeeder::class);

    expect(LiteratureSource::count())->toBe(3)
        ->and($source->fresh()->getAttributes())->toBe($before)
        ->and($this->draft->literatureSources()->where('literature_source_id', $source->id)->count())->toBe(1);
});

test('conflicting reference dates roll back sources links document changes and history', function () {
    $conflict = LiteratureSource::create([
        'fingerprint' => LiteratureSource::fingerprintFor('10.1016/j.wroa.2024.100298', '', 2025),
        'added_by' => User::factory()->create()->id,
        'title' => 'Laboratory performance assessment of low-cost water level sensor for field monitoring in the tropics',
        'authors' => 'N. Ding; Q. Zhu; F. Cherqui; N. Walcker; J.-L. Bertrand-Krajewski; P. Hamel',
        'publication_year' => 2025, 'publication_date' => '2024-12-26',
        'venue' => 'Water Research X', 'volume' => '27', 'pages' => 'Art. no. 100298', 'publisher' => 'Elsevier',
        'doi' => '10.1016/j.wroa.2024.100298', 'url' => 'https://www.sciencedirect.com/science/article/pii/S2589914724000872',
        'provider' => 'Existing shared library',
    ])->refresh();
    $before = $this->document->getAttributes();
    $conflictBefore = $conflict->getAttributes();

    expect(fn () => app()->call([app(VerifiedFloodLiteratureSeeder::class), 'run']))
        ->toThrow(RuntimeException::class, 'Existing source metadata differs');

    expect(LiteratureSource::count())->toBe(1)
        ->and($conflict->fresh()->getAttributes())->toBe($conflictBefore)
        ->and($this->draft->literatureSources()->count())->toBe(0)
        ->and($this->document->fresh()->getAttributes())->toBe($before)
        ->and($this->document->versions()->count())->toBe(0);
});

test('verified literature preserves an unsnapshotted working copy when history already exists', function () {
    $baseline = $this->document->source_data;
    $this->document->update(['source_data' => ['related_literature' => 'An older version.']]);
    app(RecordProposalDraftDocumentVersion::class)->handle($this->document, $this->draft->owner);
    $this->document->update(['source_data' => $baseline, 'lock_version' => 2]);

    $this->seed(VerifiedFloodLiteratureSeeder::class);

    $versions = $this->document->versions()->reorder('version_number')->get();
    expect($versions)->toHaveCount(3)
        ->and($versions[1]->source_data)->toEqual($baseline)
        ->and($versions[2]->source_data)->toEqual($this->document->fresh()->source_data);
});
