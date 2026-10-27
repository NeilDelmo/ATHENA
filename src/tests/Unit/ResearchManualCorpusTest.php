<?php

use App\Services\ResearchKnowledgeService;
use App\Services\ResearchManualRetriever;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $path = getenv('ATHENA_MANUAL_CORPUS_PATH') ?: storage_path('app/private/research-manual/index.json');

    if (! is_file($path)) {
        $this->markTestSkipped('The optional private Research Manual corpus is unavailable.');
    }

    config([
        'cache.default' => 'array',
        'research_assistant.manual.enabled' => true,
        'research_assistant.manual.index_path' => $path,
        'research_assistant.manual.maximum_chunks' => 4,
        'research_assistant.manual.minimum_score' => 1,
        'research_assistant.manual.retrieval_cache_seconds' => 3600,
    ]);
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
});

/** @return array<string, array{question: string, prefixes: list<string>, pages: array{int, int}, recent_messages?: list<array{role: string, content: string}>}> */
function researchManualCorpusQuestions(): array
{
    $relatedLiteratureSections = ['11. BatStateU Research Agenda:', 'Detailed Research Proposal', 'DETAILED RESEARCH PROPOSAL', 'XI. Review of Related Literature', 'Review of Related Literature'];

    return [
        'approval-route' => ['question' => 'What route does a research proposal follow for approval?', 'prefixes' => ['3.2', '3.3'], 'pages' => [32, 33]],
        'lrec-approval' => ['question' => 'How is a research proposal approved through LREC?', 'prefixes' => ['3.3'], 'pages' => [33, 33]],
        'submission-documents' => ['question' => 'What documents must I submit with my research proposal?', 'prefixes' => ['3.5', '1. Complete', '30 Activities'], 'pages' => [34, 34]],
        'rrl-minimum-approval' => ['question' => 'How many RRL do I need for my research to be approved?', 'prefixes' => $relatedLiteratureSections, 'pages' => [92, 92]],
        'rrl-related-literature-count' => ['question' => 'How many related literature sources are required for proposal approval?', 'prefixes' => $relatedLiteratureSections, 'pages' => [92, 92]],
        'rrl-ten-approval' => ['question' => 'Is 10 RRL enough for approval?', 'prefixes' => $relatedLiteratureSections, 'pages' => [92, 92]],
        'rrl-reference-recency' => ['question' => 'How recent should my RRL references be?', 'prefixes' => $relatedLiteratureSections, 'pages' => [92, 92]],
        'grant-eligibility' => ['question' => 'Who may apply for a university research project grant?', 'prefixes' => ['3.1'], 'pages' => [32, 32]],
        'permanent-faculty' => ['question' => 'Are permanent faculty with a masters degree eligible for research grants?', 'prefixes' => ['3.1'], 'pages' => [32, 32]],
        'participant-consent' => ['question' => 'Does research with human participants need informed consent?', 'prefixes' => ['3.5.2', '3.5 Criteria', '5. Other'], 'pages' => [35, 35]],
        'ethics-clearance' => ['question' => 'When should ethical clearance be secured?', 'prefixes' => ['6.4'], 'pages' => [53, 53]],
        'gad-checklist' => ['question' => 'Is a GAD checklist required when a research proposal is screened?', 'prefixes' => ['3.5', '30 Activities'], 'pages' => [34, 34]],
        'gender-responsiveness' => ['question' => 'How is gender responsiveness considered during initial proposal screening?', 'prefixes' => ['3.5', '30 Activities'], 'pages' => [34, 34]],
        'honoraria-entitlement' => ['question' => 'When are faculty researchers entitled to honoraria?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.2'], 'pages' => [45, 45]],
        'honorarium-amount' => ['question' => 'How is the honorarium for a faculty research project calculated?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.2', '3.6'], 'pages' => [36, 45]],
        'publication-incentive' => ['question' => 'What are the conditions for receiving a research publication incentive?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.5'], 'pages' => [46, 49]],
        'publication-reward' => ['question' => 'What must I submit to claim a reward for publishing a research paper?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.5'], 'pages' => [46, 49]],
        'ip-ownership' => ['question' => 'Who owns the intellectual property from a university funded research project?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.6'], 'pages' => [49, 49]],
        'invention-ownership' => ['question' => 'Who owns an invention developed through a university funded research project?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.6'], 'pages' => [49, 49]],
        'progress-report-frequency' => ['question' => 'How often must I submit a progress report for my research project?', 'prefixes' => ['4.4 Monitoring System / 4.4.2'], 'pages' => [39, 39]],
        'ongoing-study-updates' => ['question' => 'How often must a project leader submit updates on an ongoing study?', 'prefixes' => ['4.4 Monitoring System / 4.4.2'], 'pages' => [39, 39]],
        'notice-to-proceed' => ['question' => 'When is the Notice to Proceed issued for an approved research project?', 'prefixes' => ['4.2'], 'pages' => [38, 38]],
        'implementation-authorization' => ['question' => 'My proposal has been approved. What authorization is required before implementation starts?', 'prefixes' => ['4.2'], 'pages' => [38, 38]],
        'section-monitoring' => ['question' => 'What does section 4.4 of the Research Manual say?', 'prefixes' => ['4.4'], 'pages' => [39, 40]],
        'section-monitoring-structure' => ['question' => 'Explain section 4.4.1 of the Research Manual.', 'prefixes' => ['4.4 Monitoring System / 4.4.1'], 'pages' => [39, 39]],
        'section-publication-fees' => ['question' => 'What does section 5.3.5 say about publication fees?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.5'], 'pages' => [48, 49]],
        'buy-equipment' => ['question' => 'According to the Research Manual how do I buy equipment?', 'prefixes' => ['4.3'], 'pages' => [38, 39]],
        'acquire-equipment' => ['question' => 'What is the process for acquiring materials and equipment for my study?', 'prefixes' => ['4.3'], 'pages' => [38, 39]],
        'publication-followup' => [
            'question' => 'How much is it?', 'prefixes' => ['5.3 Privileges and Incentives for Faculty Researchers / 5.3.5'], 'pages' => [46, 49],
            'recent_messages' => [
                ['role' => 'user', 'content' => 'What research publication incentives can faculty receive?'],
                ['role' => 'assistant', 'content' => 'Publication incentives depend on the applicable conditions.'],
                ['role' => 'user', 'content' => 'What about eligibility?'],
                ['role' => 'assistant', 'content' => 'Eligibility is assessed under the publication incentive guidelines.'],
            ],
        ],
        'unrelated-recipe' => ['question' => 'How do I bake banana bread?', 'prefixes' => [], 'pages' => [0, 0]],
        'unrelated-html' => ['question' => 'Does this application render HTML?', 'prefixes' => [], 'pages' => [0, 0]],
        'unsupported-cryptocurrency' => ['question' => 'What is the university cryptocurrency reimbursement ceiling?', 'prefixes' => [], 'pages' => [0, 0]],
        'absent-section' => ['question' => 'What does section 99.99 of the Research Manual require?', 'prefixes' => [], 'pages' => [0, 0]],
    ];
}

test('the supplied manual corpus retrieves authoritative passages within the top three', function () {
    $retriever = app(ResearchManualRetriever::class);
    $hits = 0;
    $originalHits = 0;
    $submissionHit = false;
    $relatedLiteratureHits = 0;
    $relatedLiteraturePositives = 0;
    $positives = 0;
    $negativeCount = 0;
    $misses = [];
    $falsePositives = [];
    $warmTimes = [];
    $coldTime = null;

    foreach (researchManualCorpusQuestions() as $label => $case) {
        $start = hrtime(true);
        $sources = $retriever->retrieve($case['question'], $case['recent_messages'] ?? []);
        $coldTime ??= (hrtime(true) - $start) / 1_000_000;
        $topThree = array_slice($sources, 0, 3);
        $metadata = array_map(fn (array $source): array => ['section' => $source['section'], 'pages' => [$source['page_start'], $source['page_end']]], $topThree);

        if ($case['prefixes'] === []) {
            $negativeCount++;

            if ($sources !== []) {
                $falsePositives[$label] = $metadata;
            }
        } else {
            $positives++;

            if (Str::startsWith($label, 'rrl-')) {
                $relatedLiteraturePositives++;
            }

            $matched = collect($topThree)->contains(fn (array $source): bool => Str::startsWith($source['section'], $case['prefixes'])
                && $source['page_start'] <= $case['pages'][1] && $source['page_end'] >= $case['pages'][0]);

            if ($matched) {
                $hits++;

                if ($label === 'submission-documents') {
                    $submissionHit = true;
                } elseif (Str::startsWith($label, 'rrl-')) {
                    $relatedLiteratureHits++;
                } else {
                    $originalHits++;
                }
            } else {
                $misses[$label] = $metadata;
            }
        }

        for ($repeat = 0; $repeat < 3; $repeat++) {
            $start = hrtime(true);
            $retriever->retrieve($case['question'], $case['recent_messages'] ?? []);
            $warmTimes[] = (hrtime(true) - $start) / 1_000_000;
        }
    }

    sort($warmTimes);
    $summary = json_encode([
        'cache_driver' => 'array', 'questions' => $positives + $negativeCount,
        'hit_at_3' => $hits.'/'.$positives, 'negative_false_positives' => count($falsePositives).'/'.$negativeCount,
        'unchanged_cases_hit_at_3' => $originalHits.'/'.($positives - 1 - $relatedLiteraturePositives), 'document_submission_hit_at_3' => $submissionHit,
        'related_literature_hit_at_3' => $relatedLiteratureHits.'/'.$relatedLiteraturePositives,
        'first_call_ms' => round($coldTime, 3), 'warm_median_ms' => round($warmTimes[(int) floor(count($warmTimes) / 2)], 3),
    ], JSON_THROW_ON_ERROR);
    fwrite(STDERR, "Manual corpus benchmark: {$summary}\n");

    Http::assertNothingSent();
    Process::assertNothingRan();
    expect($misses)->toBe([], $summary)
        ->and($falsePositives)->toBe([], $summary);
});

test('the supplied manual corpus ranks the explicit RRL minimum first for count questions', function (string $question) {
    $sources = app(ResearchManualRetriever::class)->retrieve($question);

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['page_start'])->toBe(92)
        ->and($sources[0]['content'])->toContain('minimum of ten');
})->with([
    'How many RRL do I need for my research to be approved?',
    'What is the minimum for my RRL part?',
    'How many references belong in the related literature section?',
    'Do I need 10 RRL?',
]);

test('the optional manual file cache benchmark reports cold and repeated lookup latency', function () {
    if (getenv('ATHENA_MANUAL_CORPUS_FILE_BENCHMARK') !== '1') {
        $this->markTestSkipped('Set ATHENA_MANUAL_CORPUS_FILE_BENCHMARK=1 to measure the file cache.');
    }

    $temporaryRoot = realpath(sys_get_temp_dir());
    $directory = $temporaryRoot.DIRECTORY_SEPARATOR.'athena-manual-corpus-'.Str::uuid();
    config(['cache.default' => 'manual_corpus_file', 'cache.stores.manual_corpus_file' => ['driver' => 'file', 'path' => $directory]]);

    try {
        $retriever = app(ResearchManualRetriever::class);
        $question = 'What ethical clearance is needed?';
        $start = hrtime(true);
        $sources = $retriever->retrieve($question);
        $cold = (hrtime(true) - $start) / 1_000_000;
        $times = [];

        for ($repeat = 0; $repeat < 20; $repeat++) {
            $start = hrtime(true);
            $retriever->retrieve($question);
            $times[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($times);
        $summary = json_encode(['cache_driver' => 'file', 'first_call_ms' => round($cold, 3), 'warm_median_ms' => round($times[10], 3), 'warm_p95_ms' => round($times[18], 3)], JSON_THROW_ON_ERROR);
        fwrite(STDERR, "Manual corpus latency: {$summary}\n");
        expect($sources)->not->toBeEmpty();
    } finally {
        $resolvedDirectory = realpath($directory);

        if (is_string($resolvedDirectory) && str_starts_with($resolvedDirectory, $temporaryRoot.DIRECTORY_SEPARATOR.'athena-manual-corpus-')) {
            File::deleteDirectory($resolvedDirectory);
        }
    }
});

test('the optional manual database cache benchmark reports isolated local lookup latency', function () {
    if (getenv('ATHENA_MANUAL_CORPUS_DATABASE_BENCHMARK') !== '1') {
        $this->markTestSkipped('Set ATHENA_MANUAL_CORPUS_DATABASE_BENCHMARK=1 to measure the database cache.');
    }

    if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'athena_testing') {
        $this->markTestSkipped('The database benchmark requires the local athena_testing database.');
    }

    config([
        'cache.default' => 'database',
        'cache.prefix' => 'athena-manual-corpus-'.Str::uuid().':',
        'cache.stores.database.connection' => null,
    ]);
    DB::beginTransaction();

    try {
        $retriever = app(ResearchManualRetriever::class);
        $question = 'What ethical clearance is needed?';
        $start = hrtime(true);
        $sources = $retriever->retrieve($question);
        $cold = (hrtime(true) - $start) / 1_000_000;
        $times = [];

        for ($repeat = 0; $repeat < 20; $repeat++) {
            $start = hrtime(true);
            $retriever->retrieve($question);
            $times[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($times);
        $summary = json_encode([
            'cache_driver' => 'database', 'database' => 'athena_testing',
            'first_call_ms' => round($cold, 3), 'warm_median_ms' => round($times[10], 3), 'warm_p95_ms' => round($times[18], 3),
        ], JSON_THROW_ON_ERROR);
        fwrite(STDERR, "Manual corpus local database latency: {$summary}\n");
        expect($sources)->not->toBeEmpty();
        Http::assertNothingSent();
        Process::assertNothingRan();
    } finally {
        DB::rollBack();
    }
});

test('authoritative corpus passages survive the default aggregate knowledge budget', function () {
    if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'athena_testing') {
        $this->markTestSkipped('The aggregate retrieval test requires the local athena_testing database.');
    }

    config(['research_assistant.manual.maximum_context_tokens' => 8000]);
    DB::beginTransaction();

    try {
        $knowledge = app(ResearchKnowledgeService::class);
        $misses = [];
        $invalidContexts = [];
        $positives = 0;
        $maximumContextBytes = 0;

        foreach (researchManualCorpusQuestions() as $label => $case) {
            if ($case['prefixes'] === []) {
                continue;
            }

            $positives++;
            $recentMessages = $case['recent_messages'] ?? [];
            $sources = $knowledge->retrieve($case['question'], null, null, $recentMessages);
            $manualSources = collect($sources)->where('type', 'Official Research Manual');
            $matched = $manualSources->contains(fn (array $source): bool => Str::startsWith($source['section'], $case['prefixes'])
                && $source['page_start'] <= $case['pages'][1] && $source['page_end'] >= $case['pages'][0]);

            if (! $matched) {
                $misses[$label] = $manualSources->map(fn (array $source): array => ['section' => $source['section'], 'pages' => [$source['page_start'], $source['page_end']]])->values()->all();
            }

            $context = $knowledge->promptContext($sources, $case['question'], $recentMessages);
            $bytes = strlen($context ?? '');
            $maximumContextBytes = max($maximumContextBytes, $bytes);

            if ($context === null || $bytes > 8000) {
                $invalidContexts[$label] = ['context_bytes' => $bytes, 'is_null' => $context === null];
            }
        }

        $summary = json_encode([
            'cache_driver' => 'array', 'database' => 'athena_testing', 'questions' => $positives,
            'budget_survival' => ($positives - count($misses)).'/'.$positives,
            'maximum_context_bytes' => $maximumContextBytes, 'context_byte_limit' => 8000,
        ], JSON_THROW_ON_ERROR);
        fwrite(STDERR, "Manual corpus aggregate budget: {$summary}\n");
        Http::assertNothingSent();
        Process::assertNothingRan();
        expect($misses)->toBe([], $summary)
            ->and($invalidContexts)->toBe([], $summary);
    } finally {
        DB::rollBack();
    }
});
