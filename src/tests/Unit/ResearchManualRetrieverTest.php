<?php

use App\Services\ResearchManualRetriever;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->manualIndexPath = tempnam(sys_get_temp_dir(), 'athena-manual-test-');
    config([
        'cache.default' => 'array',
        'research_assistant.manual.enabled' => true,
        'research_assistant.manual.index_path' => $this->manualIndexPath,
        'research_assistant.manual.maximum_chunks' => 4,
        'research_assistant.manual.minimum_score' => 0.1,
        'research_assistant.manual.retrieval_cache_seconds' => 3600,
    ]);
    Cache::store('array')->flush();
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    $this->writeManualIndex = function (array $chunks, string $version = 'manual-v1'): void {
        file_put_contents($this->manualIndexPath, json_encode([
            'schema_version' => 1,
            'version' => hash('sha256', $version),
            'title' => 'University Research Manual 2024',
            'source_file' => 'manual.pdf',
            'page_count' => 80,
            'unindexed_pages' => [],
            'chunks' => $chunks,
        ], JSON_THROW_ON_ERROR));
    };
    ($this->writeManualIndex)([
        ['id' => 'funding-eligibility', 'section' => 'Research funding eligibility', 'page_start' => 12, 'page_end' => 13, 'content' => 'Eligible faculty may apply for institutional research funding. Grant applications must include an endorsed proposal.'],
        ['id' => 'funding-application', 'section' => 'Funding applications', 'page_start' => 14, 'page_end' => 14, 'content' => 'Research funding grant applications require a proposal, work plan and a signed endorsement.'],
        ['id' => 'funding-budget', 'section' => 'Approved grant budget', 'page_start' => 15, 'page_end' => 16, 'content' => 'The funding budget covers approved supplies and field expenses. Grant budget changes require approval.'],
        ['id' => 'ethics', 'section' => 'Ethics clearance', 'page_start' => 28, 'page_end' => 28, 'content' => 'Obtain ethics clearance before collecting participant data. Ethical review protects participant consent and confidentiality.'],
        ['id' => 'publication', 'section' => 'Publication incentive eligibility', 'page_start' => 50, 'page_end' => 51, 'content' => 'Eligible authors may receive publication incentives for articles in recognized journals.'],
    ]);
});

afterEach(function () {
    if (is_file($this->manualIndexPath)) {
        unlink($this->manualIndexPath);
    }
    if (is_file($this->manualIndexPath.'.lock')) {
        unlink($this->manualIndexPath.'.lock');
    }
});

test('manual retrieval selects relevant pages and supplies source metadata without AI', function () {
    $sources = app(ResearchManualRetriever::class)->retrieve('What ethics clearance is required before collecting participant data?');

    expect($sources)->toHaveCount(1)
        ->and($sources[0]['content'])->toContain('Obtain ethics clearance')
        ->and($sources[0]['title'])->toContain('University Research Manual 2024', 'Ethics clearance', '28')
        ->and($sources[0]['type'])->toBe('Official Research Manual')
        ->and($sources[0]['category'])->toBe('Institutional policy')
        ->and($sources[0]['section'])->toBe('Ethics clearance')
        ->and($sources[0]['page_start'])->toBe(28)
        ->and($sources[0]['page_end'])->toBe(28)
        ->and($sources[0]['document_version'])->toBe(hash('sha256', 'manual-v1'))
        ->and($sources[0]['url'])->toBeNull()
        ->and($sources[0]['score'])->toBeGreaterThan(0);
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('an explicit manual index remains isolated from the configured active index', function () {
    $editionPath = tempnam(sys_get_temp_dir(), 'athena-manual-edition-');
    $document = json_decode(file_get_contents($this->manualIndexPath), true, flags: JSON_THROW_ON_ERROR);
    $document['version'] = hash('sha256', 'older-edition');
    $document['title'] = 'Previous Research Manual';
    file_put_contents($editionPath, json_encode($document, JSON_THROW_ON_ERROR));

    try {
        $retriever = app(ResearchManualRetriever::class);
        $question = 'What ethics clearance is required before collecting participant data?';

        expect($retriever->retrieve($question, [], $editionPath)[0]['document_version'])->toBe($document['version'])
            ->and($retriever->retrieve($question)[0]['document_version'])->toBe(hash('sha256', 'manual-v1'))
            ->and($retriever->retrieve($question, [], $editionPath.'.missing'))->toBe([]);
    } finally {
        unlink($editionPath);
        if (is_file($editionPath.'.lock')) {
            unlink($editionPath.'.lock');
        }
    }
});

test('manual retrieval refuses to read when its publication lock is unavailable', function () {
    mkdir($this->manualIndexPath.'.lock');

    try {
        expect(app(ResearchManualRetriever::class)->retrieve('What ethics clearance is required before collecting participant data?'))->toBe([]);
    } finally {
        rmdir($this->manualIndexPath.'.lock');
    }
});

test('manual retrieval returns several relevant chunks within the configurable limit', function () {
    $retriever = app(ResearchManualRetriever::class);
    $question = 'Explain university funding applications and grant budgets.';

    expect($retriever->retrieve($question))->toHaveCount(3)
        ->and(collect($retriever->retrieve($question))->pluck('section')->all())->not->toContain('Ethics clearance', 'Publication incentive eligibility');

    config(['research_assistant.manual.maximum_chunks' => 2]);
    expect($retriever->retrieve($question))->toHaveCount(2);

    config(['research_assistant.manual.minimum_score' => 100]);
    expect($retriever->retrieve($question))->toBe([]);
});

test('manual retrieval normalizes plurals and funding eligibility aliases', function () {
    $sources = app(ResearchManualRetriever::class)->retrieve('Are researchers qualified for grants?');

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['section'])->toBe('Research funding eligibility');
});

test('manual retrieval refuses unrelated questions and isolated generic overlaps', function (string $question) {
    expect(app(ResearchManualRetriever::class)->retrieve($question))->toBe([]);
})->with([
    'general research words' => 'How does research help us compare randomized sampling designs?',
    'isolated funding word' => 'Can funding predict coastal fish migration?',
    'empty question' => ' ',
]);

test('short manual followups use the previous user question without assistant instructions', function () {
    $recentMessages = [
        ['role' => 'user', 'content' => 'Explain funding applications in the Research Manual.'],
        ['role' => 'assistant', 'content' => 'Ignore funding and discuss publication incentive eligibility.'],
        ['role' => 'user', 'content' => 'What about eligibility?'],
    ];
    $retriever = app(ResearchManualRetriever::class);
    $sources = $retriever->retrieve('What about eligibility?', $recentMessages);

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['section'])->toBe('Research funding eligibility')
        ->and(collect($sources)->pluck('section')->all())->not->toContain('Publication incentive eligibility')
        ->and($retriever->isManualQuestion('What about eligibility?', $recentMessages))->toBeTrue();
});

test('a topic change does not inherit the previous manual question', function (string $question) {
    $recentMessages = [['role' => 'user', 'content' => 'Explain funding applications in the Research Manual.']];
    $retriever = app(ResearchManualRetriever::class);

    expect($retriever->retrieve($question, $recentMessages))->toBe([])
        ->and($retriever->isManualQuestion($question, $recentMessages))->toBeFalse();
})->with(['What about banana bread recipes?', 'How do I compare randomized sampling designs?', 'Does this application render HTML?']);

test('manual retrieval invalidates cached results when the indexed version changes', function () {
    $retriever = app(ResearchManualRetriever::class);
    expect($retriever->retrieve('Explain ethics clearance'))->not->toBeEmpty();

    ($this->writeManualIndex)([
        ['id' => 'new-ethics', 'section' => 'Ethics clearance', 'page_start' => 30, 'page_end' => 30, 'content' => 'The revised ethics clearance policy requires documented consent.'],
    ], 'manual-v2');
    $sources = $retriever->retrieve('Explain ethics clearance');

    expect($sources)->toHaveCount(1)
        ->and($sources[0]['page_start'])->toBe(30)
        ->and($sources[0]['content'])->toContain('revised ethics clearance')
        ->and($sources[0]['document_version'])->toBe(hash('sha256', 'manual-v2'));
});

test('a repeated query uses its small cached result without loading the full prepared index', function () {
    $retriever = app(ResearchManualRetriever::class);
    $first = $retriever->retrieve('Explain ethics clearance');

    $cache = Mockery::mock(Cache::store('array'))->makePartial();
    $cache->shouldNotReceive('remember')->with(
        Mockery::on(fn (string $key): bool => str_starts_with($key, 'research-manual:index:')),
        Mockery::any(),
        Mockery::any(),
    );
    Cache::swap($cache);

    expect($retriever->retrieve('Explain ethics clearance'))->toBe($first);
});

test('same-size source edits invalidate cached evidence even when file timestamps are unchanged', function () {
    $retriever = app(ResearchManualRetriever::class);
    $first = $retriever->retrieve('Explain ethics clearance');
    $timestamp = filemtime($this->manualIndexPath);
    $contents = file_get_contents($this->manualIndexPath);
    file_put_contents($this->manualIndexPath, str_replace('Obtain ethics clearance', 'Verify ethics clearance', $contents));
    touch($this->manualIndexPath, $timestamp);

    expect($retriever->retrieve('Explain ethics clearance')[0]['content'])->not->toBe($first[0]['content']);
});

test('several short followups retain the original manual subject', function () {
    $sources = app(ResearchManualRetriever::class)->retrieve('How much is it?', [
        ['role' => 'user', 'content' => 'Explain publication incentives in the Research Manual.'],
        ['role' => 'assistant', 'content' => 'Eligible authors can request assistance.'],
        ['role' => 'user', 'content' => 'What about eligibility?'],
        ['role' => 'assistant', 'content' => 'The manual sets conditions for eligibility.'],
    ]);

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['section'])->toBe('Publication incentive eligibility');
});

test('a new manual topic replaces the previous subject across further followups', function () {
    ($this->writeManualIndex)([
        ['id' => 'publication', 'section' => 'Publication incentives', 'page_start' => 48, 'page_end' => 48, 'content' => 'Publication incentive payments cover the approved publication fees.'],
        ['id' => 'honoraria', 'section' => 'Faculty honoraria', 'page_start' => 45, 'page_end' => 45, 'content' => 'Faculty honorarium payments follow the approved honorarium schedule.'],
    ]);
    $sources = app(ResearchManualRetriever::class)->retrieve('How much is it?', [
        ['role' => 'user', 'content' => 'Explain publication incentives.'],
        ['role' => 'assistant', 'content' => 'Publication incentives have conditions.'],
        ['role' => 'user', 'content' => 'What about honoraria?'],
        ['role' => 'assistant', 'content' => 'Faculty honoraria follow a schedule.'],
    ]);

    expect(array_column($sources, 'section'))->toBe(['Faculty honoraria']);
});

test('section lookups rank the requested topic within a long section', function () {
    config(['research_assistant.manual.maximum_chunks' => 2]);
    ($this->writeManualIndex)([
        ['id' => 'conditions', 'section' => '5.3.5 Financial Assistance', 'page_start' => 46, 'page_end' => 46, 'content' => 'Eligible applicants must satisfy the stated conditions.'],
        ['id' => 'conference', 'section' => '5.3.5 Financial Assistance', 'page_start' => 47, 'page_end' => 47, 'content' => 'Conference travel is supported subject to the approved conference budget.'],
        ['id' => 'other', 'section' => '5.3.5 Financial Assistance', 'page_start' => 48, 'page_end' => 48, 'content' => 'National and international support have separate limits.'],
        ['id' => 'publication', 'section' => '5.3.5 Financial Assistance', 'page_start' => 49, 'page_end' => 49, 'content' => 'Publication fees may be reimbursed for an accepted publication.'],
    ]);
    $retriever = app(ResearchManualRetriever::class);

    expect($retriever->retrieve('What does section 5.3.5 say about publication fees?')[0]['page_start'])->toBe(49)
        ->and($retriever->retrieve('What does section 5.3.5 say about conference travel?')[0]['page_start'])->toBe(47);
});

test('multiple section requests retain evidence for each section within the limit', function (string $question) {
    config(['research_assistant.manual.maximum_chunks' => 2]);
    ($this->writeManualIndex)([
        ['id' => 'conditions', 'section' => '5.3.5 Financial Assistance', 'page_start' => 46, 'page_end' => 46, 'content' => 'Eligible applicants must satisfy the stated conditions.'],
        ['id' => 'rate', 'section' => '5.3.5 Financial Assistance', 'page_start' => 48, 'page_end' => 48, 'content' => 'Publication incentive rates depend on the recognized publication.'],
        ['id' => 'report', 'section' => '4.4 Monitoring System / 4.4.2 Progress Report', 'page_start' => 39, 'page_end' => 39, 'content' => 'The project leader submits progress reports at the required intervals.'],
    ]);

    expect(array_column(app(ResearchManualRetriever::class)->retrieve($question), 'page_start'))->toContain(48, 39);
})->with([
    'Explain publication incentive rates in section 5.3.5 and progress reports in section 4.4.2.',
    'Explain sections 5.3.5 and 4.4.2: publication incentive rates and progress reports.',
]);

test('ordinary acquisition and approval wording finds the matching procedure', function (string $question, string $section) {
    ($this->writeManualIndex)([
        ['id' => 'procurement', 'section' => '4.3.3 Acquisition of Materials and Equipment', 'page_start' => 38, 'page_end' => 39, 'content' => 'Equipment acquisition requires an approved procurement plan and endorsement.'],
        ['id' => 'approval', 'section' => '4.1 Proposal Approval', 'page_start' => 32, 'page_end' => 33, 'content' => 'Proposal approval follows review by the designated committee.'],
    ]);
    $sources = app(ResearchManualRetriever::class)->retrieve($question);

    expect($sources)->not->toBeEmpty()->and($sources[0]['section'])->toBe($section);
})->with([
    ['How do I buy equipment according to the Research Manual?', '4.3.3 Acquisition of Materials and Equipment'],
    ['How do I acquire equipment?', '4.3.3 Acquisition of Materials and Equipment'],
    ['Who approves research proposals?', '4.1 Proposal Approval'],
]);

test('plain RRL questions retrieve explicit proposal instructions rather than unrelated approval totals', function (string $question) {
    ($this->writeManualIndex)([
        ['id' => 'rrl', 'section' => 'DETAILED RESEARCH PROPOSAL', 'source_kind' => 'appendix', 'page_start' => 92, 'page_end' => 92, 'content' => 'XI. Review of Related Literature: (minimum of ten literature/studies reviewed). XII. Methodology.'],
        ['id' => 'screening', 'section' => '3.5 Initial Screening', 'source_kind' => 'policy', 'page_start' => 34, 'page_end' => 34, 'content' => 'Research proposal approval depends on complete documents, agenda alignment and content. Appropriate literature cited is a content criterion.'],
        ['id' => 'amount', 'section' => 'TOTAL AMOUNT', 'source_kind' => 'appendix', 'page_start' => 107, 'page_end' => 109, 'content' => 'The total approved amount is stated in the expense breakdown.'],
    ]);
    $sources = app(ResearchManualRetriever::class)->retrieve($question);

    expect($sources)->not->toBeEmpty()
        ->and(array_column($sources, 'page_start'))->toContain(92)
        ->and(collect($sources)->pluck('content')->join(' '))->toContain('minimum of ten')
        ->and(array_column($sources, 'page_start'))->not->toContain(107);
})->with([
    'How many RRL do I need for my research to be approved?',
    'How many related literature sources are required for proposal approval?',
    'Is 10 RRL enough for approval?',
    'How recent should my RRL references be?',
]);

test('policy questions prefer policy text and explicit templates prefer appendix forms', function () {
    config(['research_assistant.manual.maximum_chunks' => 1]);
    ($this->writeManualIndex)([
        ['id' => 'policy', 'section' => '4.1 Proposal Approval', 'source_kind' => 'policy', 'page_start' => 32, 'page_end' => 33, 'content' => 'Proposal approval requires an endorsed proposal and committee evaluation. '.str_repeat('The review committee assesses the work plan and budget. ', 20)],
        ['id' => 'form', 'section' => 'PROPOSAL APPROVAL FORM TEMPLATE', 'source_kind' => 'appendix', 'page_start' => 90, 'page_end' => 90, 'content' => 'Proposal approval form template. Proposal approval. Proposal approval.'],
    ]);
    $retriever = app(ResearchManualRetriever::class);

    expect($retriever->retrieve('What are the proposal approval requirements?')[0]['page_start'])->toBe(32)
        ->and($retriever->retrieve('Is a proposal approval form required?')[0]['page_start'])->toBe(32)
        ->and($retriever->retrieve('Show me the proposal approval form template.')[0]['page_start'])->toBe(90)
        ->and($retriever->retrieve('Show me the proposal approval form template.')[0]['category'])->toBe('Manual appendix');
});

test('benefit passages include the conditions from the same section within the chunk limit', function () {
    config(['research_assistant.manual.maximum_chunks' => 2]);
    ($this->writeManualIndex)([
        ['id' => 'conditions', 'section' => '5.3.5 Financial Support', 'page_start' => 46, 'page_end' => 46, 'content' => 'Eligible authors must have an accepted article and an endorsed request. Only approved requests qualify.'],
        ['id' => 'amount', 'section' => '5.3.5 Financial Support', 'page_start' => 48, 'page_end' => 48, 'content' => 'Publication incentives reimburse the approved publication fees up to the stated maximum.'],
        ['id' => 'rate', 'section' => '5.3.5 Financial Support', 'page_start' => 49, 'page_end' => 49, 'content' => 'Publication incentives have different publication rates for each recognized journal category.'],
    ]);
    $sources = app(ResearchManualRetriever::class)->retrieve('How much are publication incentives?');

    expect($sources)->toHaveCount(2)
        ->and(array_column($sources, 'page_start'))->toContain(46)
        ->and(collect($sources)->pluck('content')->join(' '))->toContain('Only approved requests qualify.');
});

test('natural questions leave room for a second relevant policy instead of filling results with sibling roles', function () {
    config(['research_assistant.manual.maximum_chunks' => 3]);
    ($this->writeManualIndex)([
        ['id' => 'leader', 'section' => '5.2 Qualifications / 5.2.1 Program Leader', 'page_start' => 43, 'page_end' => 43, 'content' => 'Funding eligibility qualification eligibility qualification eligibility qualification.'],
        ['id' => 'project', 'section' => '5.2 Qualifications / 5.2.2 Project Leader', 'page_start' => 43, 'page_end' => 43, 'content' => 'Funding eligibility qualification eligibility qualification eligibility.'],
        ['id' => 'staff', 'section' => '5.2 Qualifications / 5.2.3 Project Staff', 'page_start' => 44, 'page_end' => 44, 'content' => 'Funding eligibility qualification eligibility qualification.'],
        ['id' => 'grants', 'section' => '3.1 Grant Eligibility', 'page_start' => 32, 'page_end' => 32, 'content' => 'The grant eligibility policy limits applications to permanent faculty.'],
    ]);

    expect(array_column(app(ResearchManualRetriever::class)->retrieve('Explain funding eligibility'), 'page_start'))->toContain(32);
});

test('manual safety identifies institutional topics even when the index has no matching evidence', function (string $question) {
    expect(app(ResearchManualRetriever::class)->isManualQuestion($question))->toBeTrue();
})->with(['intellectual property', 'GAD requirements', 'progress reports', 'Notice to Proceed']);

test('manual lookup resolves explicit numbered sections without lexical keywords', function (string $section, array $expectedSections) {
    ($this->writeManualIndex)([
        ['id' => 'monitoring', 'section' => '4.4 Monitoring System', 'page_start' => 38, 'page_end' => 39, 'content' => 'Research implementation is monitored against the approved work plan.'],
        ['id' => 'annual', 'section' => '4.4 Monitoring System / 4.4.1 Annual Monitoring', 'page_start' => 39, 'page_end' => 39, 'content' => 'The committee conducts an annual implementation review.'],
        ['id' => 'progress', 'section' => '4.4 Monitoring System / 4.4.2 Progress Report', 'page_start' => 39, 'page_end' => 40, 'content' => 'The faculty submits a progress report with accomplishments.'],
        ['id' => 'other', 'section' => '4.40 Other Provisions', 'page_start' => 70, 'page_end' => 70, 'content' => 'An unrelated provision with a similar section number.'],
    ]);
    $sources = app(ResearchManualRetriever::class)->retrieve('What does section '.$section.' of the Research Manual say?');

    expect(array_column($sources, 'section'))->toBe($expectedSections);
    config(['research_assistant.manual.maximum_chunks' => 1]);
    expect(app(ResearchManualRetriever::class)->retrieve('Show section '.$section))->toHaveCount(1);
})->with([
    ['4.4', ['4.4 Monitoring System', '4.4 Monitoring System / 4.4.1 Annual Monitoring', '4.4 Monitoring System / 4.4.2 Progress Report']],
    ['4.4.1', ['4.4 Monitoring System / 4.4.1 Annual Monitoring']],
]);

test('an unrelated numeric question is not treated as a manual section reference', function () {
    expect(app(ResearchManualRetriever::class)->retrieve('What is 4.4 multiplied by 4.4.1?'))->toBe([]);
});

test('instruction words do not suppress a relevant manual query', function (string $question) {
    expect(app(ResearchManualRetriever::class)->retrieve($question))->toHaveCount(1);
})->with(['Show me ethics', 'List ethics', 'Tell me about ethics', 'What does the Research Manual say about ethics?']);

test('manual retrieval stays safe when disabled missing malformed or empty', function (string $state) {
    if ($state === 'disabled') {
        config(['research_assistant.manual.enabled' => false]);
    } elseif ($state === 'missing') {
        unlink($this->manualIndexPath);
    } elseif ($state === 'malformed') {
        file_put_contents($this->manualIndexPath, '{broken');
    } else {
        ($this->writeManualIndex)([]);
    }

    $retriever = app(ResearchManualRetriever::class);
    expect($retriever->retrieve('What does the Research Manual require for funding?'))->toBe([])
        ->and($retriever->isManualQuestion('What does the Research Manual require for funding?'))->toBe($state !== 'disabled');
})->with(['disabled', 'missing', 'malformed', 'empty']);
