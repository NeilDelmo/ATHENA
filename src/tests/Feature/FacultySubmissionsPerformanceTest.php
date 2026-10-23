<?php

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

test('faculty submissions load only latest status files once while preserving GAD labels and access', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $other = User::factory()->create();
    $outcomes = [
        ['passed', true, 'Co-evaluator review'],
        ['commended', true, 'Co-evaluator review'],
        ['conditional_pass', true, 'GAD assessment'],
        ['passed', false, 'GAD assessment'],
        [null, true, 'Co-evaluator review'],
    ];
    $latestVersions = [];
    $labels = [];

    foreach ($outcomes as $index => [$outcome, $signed, $label]) {
        $owner = $index === 1 ? $other : $faculty;
        $topic = TopicProposal::create([
            'user_id' => $owner->id, 'title' => 'GAD submission '.$index,
            'status' => TopicProposal::STATUS_GAD_REVIEW, 'review_stage' => 'gad',
        ]);
        $topic->forceFill(['created_at' => now()->subMinutes($index)])->saveQuietly();
        if ($owner->is($other)) {
            $topic->collaborators()->create([
                'user_id' => $faculty->id, 'name' => $faculty->name,
                'email' => $faculty->email, 'accepted_at' => now(),
            ]);
        }
        foreach (range(1, 3) as $number) {
            $version = $topic->versions()->create([
                'submitted_by' => $owner->id, 'version_number' => $number,
                'submission_type' => $number === 1 ? 'initial' : 'revision',
                'title' => $topic->title, 'description' => str_repeat('Previous proposal narrative. ', 1000),
                'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 100,
            ]);
            $gad = $version->files()->create([
                'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'file_path' => 'gad.pdf', 'original_filename' => 'gad.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 100,
            ]);
            $assessment = [
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'gad_signature_confirmed' => $number === 3 ? $signed : true,
                'gad_score' => 15,
            ];
            if ($outcome !== null || $number !== 3) {
                $assessment['gad_outcome'] = $number === 3 ? $outcome : 'passed';
            }
            $version->files()->create([
                'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
                'source_version_file_id' => $gad->id, 'source_data' => $assessment,
                'file_path' => 'assessment.pdf', 'original_filename' => 'assessment.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 100,
            ]);
            $version->files()->create([
                'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
                'source_data' => ['rationale' => str_repeat('Full paper outside the submissions list. ', 1000)],
                'file_path' => 'detailed.pdf', 'original_filename' => 'detailed.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 100,
            ]);
        }
        $latestVersions[$topic->id] = $version->id;
        $labels[$topic->id] = $label;
    }
    foreach (range(1, 8) as $number) {
        TopicProposal::create([
            'user_id' => $faculty->id, 'title' => 'Pending submission '.$number,
            'status' => 'pending',
        ])->forceFill(['created_at' => now()->subDays($number)])->saveQuietly();
    }
    TopicProposal::create(['user_id' => $other->id, 'title' => 'Private submission', 'status' => 'pending']);

    $benchmark = getenv('ATHENA_PERFORMANCE_BENCHMARK') === '1';
    $hydratedBytes = 0;
    $retrievedEvents = ['eloquent.retrieved: '.ProposalVersion::class, 'eloquent.retrieved: '.ProposalVersionFile::class];
    if ($benchmark) {
        foreach ($retrievedEvents as $event) {
            Event::listen($event, function (Model $record) use (&$hydratedBytes): void {
                $hydratedBytes += strlen(json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));
            });
        }
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = hrtime(true);
    try {
        $response = $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
            ->get(route('faculty.submissions'))->assertOk()->assertDontSee('Private submission');
        $queries = collect(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        if ($benchmark) {
            foreach ($retrievedEvents as $event) {
                Event::forget($event);
            }
        }
    }
    if ($benchmark) {
        fwrite(STDERR, sprintf("Faculty submissions: %.1f ms, %d queries, %d hydrated version/file bytes\n", (hrtime(true) - $started) / 1_000_000, $queries->count(), $hydratedBytes));
    }

    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `proposal_version_files`')))->toHaveCount(1);
    $topics = $response->viewData('topics');
    expect($topics->total())->toBe(13)
        ->and($topics)->toHaveCount(12)
        ->and($topics->whereIn('id', array_keys($latestVersions)))->toHaveCount(5)
        ->and($topics->nextPageUrl())->toContain('category=active');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ($topics->whereIn('id', array_keys($latestVersions)) as $topic) {
        expect($topic->relationLoaded('versions'))->toBeFalse()
            ->and($topic->relationLoaded('latestVersion'))->toBeTrue()
            ->and($topic->latestVersion->id)->toBe($latestVersions[$topic->id])
            ->and($topic->latestVersion->version_number)->toBe(3)
            ->and(array_keys($topic->latestVersion->getAttributes()))->not->toContain('description')
            ->and($topic->latestVersion->relationLoaded('files'))->toBeTrue()
            ->and($topic->latestVersion->files)->toHaveCount(2)
            ->and($topic->latestVersion->files->pluck('document_type')->all())->not->toContain(ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
            ->and($xpath->query('//*[@data-submitted-proposal="'.$topic->id.'"]')->item(0)->textContent)->toContain($labels[$topic->id]);
    }
    $this->get(route('faculty.submissions', ['category' => 'active', 'page' => 2]))->assertOk()
        ->assertDontSee('Private submission')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 13 && $topics->count() === 1);
});
