<?php

use App\Services\ResearchManualIndexer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('the command refuses to replace an edition managed through the manual library', function () {
    File::put($this->manualDirectory.'/index.json', json_encode(['managed_manual_id' => 12, 'version' => str_repeat('a', 64)], JSON_THROW_ON_ERROR));
    $before = File::get($this->manualDirectory.'/index.json');

    $this->artisan('research-manual:index', ['path' => $this->manualPdf, '--force' => true])
        ->expectsOutputToContain('Research Head')
        ->assertFailed();

    expect(File::get($this->manualDirectory.'/index.json'))->toBe($before);
    Process::assertNothingRan();
});

test('manual indexing can stage an edition without replacing the active index', function () {
    Process::fake([Process::result(output: "1. Research Policy\n\nEthics approval is required.\f")]);
    $stagedPath = $this->manualDirectory.'/staged/index.json';
    $index = app(ResearchManualIndexer::class)->index($this->manualPdf, indexPath: $stagedPath);

    expect(is_file($stagedPath))->toBeTrue()
        ->and(is_file($this->manualDirectory.'/index.json'))->toBeFalse()
        ->and(File::get(dirname($stagedPath).'/manual.pdf'))->toBe(File::get($this->manualPdf))
        ->and(json_decode(File::get($stagedPath), true)['version'])->toBe($index['version']);
});

beforeEach(function () {
    $this->manualDirectory = sys_get_temp_dir().'/athena-manual-test-'.Str::uuid();
    File::ensureDirectoryExists($this->manualDirectory);
    $this->manualPdf = $this->manualDirectory.'/source.pdf';
    File::put($this->manualPdf, "%PDF-1.4\nLocal test fixture");
    config([
        'research_assistant.manual.index_path' => $this->manualDirectory.'/index.json',
        'research_assistant.manual.chunk_characters' => 300,
        'research_assistant.manual.pdftotext_binary' => 'test-pdftotext',
        'research_assistant.manual.extraction_timeout' => 120,
    ]);
    Process::preventStrayProcesses();
});

afterEach(function () {
    if (Str::startsWith($this->manualDirectory, sys_get_temp_dir().'/athena-manual-test-')) {
        File::deleteDirectory($this->manualDirectory);
    }
});

test('manual import preserves heading and PDF page boundaries while removing repeated furniture and contents', function () {
    $pages = [
        "The BatStateU The NEU Research Manual\n\nCONTENTS\n1. Introduction ........ 3\n2. Ethics Review ........ 4\n3. Funding ........ 5\n1",
        "The BatStateU The NEU Research Manual\n\n1. Introduction\n\nProposals must identify measurable outputs.\n2",
        "The BatStateU The NEU Research Manual\n\nResearch must follow the approved work plan.\n\n2. Ethics Review\n\nParticipant consent is required before recruitment.\n3",
        "The BatStateU The NEU Research Manual\n\n4",
    ];
    Process::fake(fn (PendingProcess $process) => Process::result(output: implode("\f", $pages)."\f"));

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);

    expect($index['page_count'])->toBe(4)
        ->and($index['unindexed_pages'])->toBe([4])
        ->and($index['chunks'])->toHaveCount(2)
        ->and($index['chunks'][0]['section'])->toBe('1. Introduction')
        ->and($index['chunks'][0]['page_start'])->toBe(2)
        ->and($index['chunks'][0]['page_end'])->toBe(3)
        ->and($index['chunks'][0]['content'])->toContain('measurable outputs', 'approved work plan')
        ->not->toContain('CONTENTS', 'Research Manual', 'Participant consent')
        ->and($index['chunks'][1]['section'])->toBe('2. Ethics Review')
        ->and($index['chunks'][1]['page_start'])->toBe(3)
        ->and($index['chunks'][1]['content'])->toContain('Participant consent');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'test-pdftotext', '-layout', '-enc', 'UTF-8', $this->manualPdf, '-',
    ] && $process->timeout === 120);
    expect(File::get($this->manualDirectory.'/manual.pdf'))->toBe(File::get($this->manualPdf));
});

test('manual import reuses an unchanged version and rebuilds when title chunk settings or page text change', function () {
    $pagesPath = $this->manualDirectory.'/pages.json';
    File::put($pagesPath, json_encode([
        ['number' => 1, 'text' => "4.2 Notice to Proceed\n\nWait for the signed notice before beginning activities."],
        ['number' => 2, 'text' => 'The approved notice identifies the project schedule.'],
    ], JSON_THROW_ON_ERROR));

    $first = app(ResearchManualIndexer::class)->index($this->manualPdf, pagesJsonPath: $pagesPath);
    $reused = app(ResearchManualIndexer::class)->index($this->manualPdf, pagesJsonPath: $pagesPath);
    $renamed = app(ResearchManualIndexer::class)->index($this->manualPdf, 'Updated Research Manual', pagesJsonPath: $pagesPath);
    config(['research_assistant.manual.chunk_characters' => 400]);
    $resized = app(ResearchManualIndexer::class)->index($this->manualPdf, 'Updated Research Manual', pagesJsonPath: $pagesPath);
    File::put($pagesPath, json_encode([['number' => 1, 'text' => 'Updated notice rules.']], JSON_THROW_ON_ERROR));
    $revised = app(ResearchManualIndexer::class)->index($this->manualPdf, 'Updated Research Manual', pagesJsonPath: $pagesPath);

    expect($first['reused'])->toBeFalse()
        ->and($reused['reused'])->toBeTrue()
        ->and($reused['version'])->toBe($first['version'])
        ->and($renamed['version'])->not->toBe($first['version'])
        ->and($resized['version'])->not->toBe($renamed['version'])
        ->and($revised['version'])->not->toBe($resized['version'])
        ->and(File::get($pagesPath))->toContain('Updated notice rules.');
    Process::assertNothingRan();
});

test('manual import failures preserve the previously indexed document', function () {
    Process::fake([Process::result(output: "1. Funding\n\nBudget approval is required.\f")]);
    app(ResearchManualIndexer::class)->index($this->manualPdf);
    $beforeIndex = File::get($this->manualDirectory.'/index.json');
    $beforePdf = File::get($this->manualDirectory.'/manual.pdf');
    File::put($this->manualPdf, "%PDF-1.4\nNew unreadable document");
    Process::fake([Process::result(errorOutput: 'Invalid document', exitCode: 1)]);

    expect(fn () => app(ResearchManualIndexer::class)->index($this->manualPdf))->toThrow(RuntimeException::class, 'text extraction failed');
    expect(File::get($this->manualDirectory.'/index.json'))->toBe($beforeIndex)
        ->and(File::get($this->manualDirectory.'/manual.pdf'))->toBe($beforePdf);
});

test('manual chunks remain bounded without dropping long paragraphs or assigning the following heading', function () {
    $sentences = array_fill(0, 20, 'Each activity needs a measurable output.');
    $text = "4.1 Project Activities\n\n".implode(' ', $sentences)."\n\n4.2 Notice to Proceed\n\nApproval precedes implementation.";
    Process::fake([Process::result(output: $text."\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);
    $activityChunks = array_values(array_filter($index['chunks'], fn (array $chunk): bool => $chunk['section'] === '4.1 Project Activities'));

    expect($activityChunks)->not->toBeEmpty()
        ->and(substr_count(implode(' ', array_column($activityChunks, 'content')), 'Each activity needs a measurable output.'))->toBe(20);

    foreach ($index['chunks'] as $chunk) {
        expect(Str::length($chunk['content']))->toBeLessThanOrEqual(300);
    }

    expect($index['chunks'][array_key_last($index['chunks'])]['section'])->toBe('4.2 Notice to Proceed');
});

test('manual page JSON rejects missing out of order pages rather than silently changing citations', function () {
    $pagesPath = $this->manualDirectory.'/pages.json';
    File::put($pagesPath, '[{"number":1,"text":"Funding"},{"number":3,"text":"Ethics"}]');

    expect(fn () => app(ResearchManualIndexer::class)->index($this->manualPdf, pagesJsonPath: $pagesPath))
        ->toThrow(RuntimeException::class, 'preserve every PDF page in order');
    expect(is_file($this->manualDirectory.'/index.json'))->toBeFalse();
    Process::assertNothingRan();
});

test('manual deeper headings follow the contents hierarchy while numbered policy clauses stay in their section', function () {
    Process::fake([Process::result(output: "CONTENTS\n2.1 Research Council ........ 16\n2.1.1 Composition ........ 16\n2.1.2 Functions ........ 18\f2.1 Research Council\n\nThe council supervises research.\n\n2.1.1 Composition:\n\nFaculty representatives participate.\n2.1.1.1 Each representative serves the approved term.\n\n2.1.2 Functions\n\nThe council evaluates research priorities.\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);

    expect($index['chunks'])->toHaveCount(3)
        ->and($index['chunks'][1]['section'])->toBe('2.1 Research Council / 2.1.1 Composition')
        ->and($index['chunks'][1]['content'])->toContain('2.1.1.1 Each representative')
        ->and($index['chunks'][2]['section'])->toBe('2.1 Research Council / 2.1.2 Functions');
});

test('manual appendix work instructions retain table labels under the form title', function () {
    Process::fake([Process::result(output: "ATTACHMENTS\fWork Instruction of Institutionally Funded Research\nPROCESS FLOW\nSTART\nReceiving of Proposal\nRESPONSIBILITY\nVCRDES\nDETAILS\nAccept the complete proposal.\fPROCESS FLOW\nContinue with the review.\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);

    expect(array_unique(array_column($index['chunks'], 'section')))->toBe([
        'ATTACHMENTS', 'Work Instruction of Institutionally Funded Research',
    ])
        ->and($index['chunks'][1]['content'])->toContain('PROCESS FLOW', 'VCRDES', 'Continue with the review.')
        ->and($index['chunks'][1]['page_start'])->toBe(2)
        ->and($index['chunks'][1]['page_end'])->toBe(3);
});

test('manual screening rubric rows remain under the policy heading from the contents', function () {
    config(['research_assistant.manual.chunk_characters' => 600]);
    Process::fake([Process::result(output: "CONTENTS\n3.5 Evaluation Criteria ........ 2\n3.5.1 Initial Screening ........ 2\n3.5.2 Committee Evaluation ........ 3\f3.5 Evaluation Criteria\n3.5.1. Initial Screening\nCriteria          %          Satisfactory\n3. Content                  40          20          10\n1. Clear and Concise Rationale\n2. SMART Objectives and Methodology\n3. Appropriate Literature\nCited\n4. Grammar and Spelling\nPassing Score: 80%\f3.5.2 Committee Evaluation\nThe committee considers the complete proposal.\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);
    $screening = collect($index['chunks'])->firstWhere('section', '3.5 Evaluation Criteria / 3.5.1 Initial Screening');

    expect($index['page_count'])->toBe(3)
        ->and($screening['page_start'])->toBe(2)
        ->and($screening['page_end'])->toBe(2)
        ->and($screening['content'])->toContain('3. Content', 'Appropriate Literature', 'Passing Score: 80%')
        ->and(array_column($index['chunks'], 'section'))->not->toContain('3. Content                  40          20          10', '3. Appropriate Literature')
        ->and($index['chunks'][array_key_last($index['chunks'])]['section'])->toBe('3.5 Evaluation Criteria / 3.5.2 Committee Evaluation');
});

test('manual decimal headings remain recognizable when their contents entry wraps', function () {
    Process::fake([Process::result(output: "CONTENTS\n3.1 General Guidelines ........ 2\n3.2 Types of Grants ........ 2\n3.3 Selection and Evaluation of\nResearch Proposals ........ 3\f3.2 Types of Grants\nInstitutional funding supports approved activities.\f3.3 Selection and Evaluation of Research Proposals\nThe committee reviews submissions before recommending approval.\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);
    $selection = collect($index['chunks'])->firstWhere('section', '3.3 Selection and Evaluation of Research Proposals');

    expect($selection)->not->toBeNull()
        ->and($selection['page_start'])->toBe(3)
        ->and($selection['page_end'])->toBe(3)
        ->and($selection['content'])->toContain('committee reviews submissions')
        ->and($index['chunks'][0]['content'])->not->toContain('committee reviews submissions');
});

test('manual appendix form fields preserve their paper title for literature requirements', function () {
    config(['research_assistant.manual.chunk_characters' => 400]);
    Process::fake([Process::result(output: "ATTACHMENTS\fDETAILED RESEARCH PROPOSAL\nI. Research Project Title:\n11. University Research Agenda:\nIII. Sustainable Development Goal:\n1. Publication:\nXI. Review of Related Literature: (minimum of ten literature/studies reviewed)\nXII. Methodology:\fXVI. References:\nSource details follow.\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);
    $literature = collect($index['chunks'])->first(fn (array $chunk): bool => str_contains($chunk['content'], 'minimum of ten'));

    expect($index['page_count'])->toBe(3)
        ->and($literature['section'])->toBe('DETAILED RESEARCH PROPOSAL')
        ->and($literature['source_kind'])->toBe('appendix')
        ->and($literature['page_start'])->toBe(2)
        ->and($literature['page_end'])->toBe(3)
        ->and(array_values(array_unique(array_column($index['chunks'], 'section'))))->toBe(['ATTACHMENTS', 'DETAILED RESEARCH PROPOSAL']);
    foreach ($index['chunks'] as $chunk) {
        expect(Str::length($chunk['content']))->toBeLessThanOrEqual(400);
    }
});

test('manual import distinguishes policy from appendix material at the document heading', function (string $appendixHeading) {
    Process::fake([Process::result(output: "1. Research Policy\n\nApproval is required before spending.\fAdditional policy conditions apply.\n\n{$appendixHeading}\n\nREQUEST FOR FUNDING\n\nApplicant name: ____________________\fRequested amount: ____________________\f")]);

    $index = app(ResearchManualIndexer::class)->index($this->manualPdf);

    expect($index['page_count'])->toBe(3)
        ->and($index['unindexed_pages'])->toBe([])
        ->and($index['chunks'])->toHaveCount(3)
        ->and($index['chunks'][0]['source_kind'])->toBe('policy')
        ->and($index['chunks'][0]['page_start'])->toBe(1)
        ->and($index['chunks'][0]['page_end'])->toBe(2)
        ->and($index['chunks'][0]['content'])->toContain('Additional policy conditions')
        ->not->toContain($appendixHeading, 'Applicant name')
        ->and($index['chunks'][1]['source_kind'])->toBe('appendix')
        ->and($index['chunks'][1]['section'])->toBe($appendixHeading)
        ->and($index['chunks'][1]['page_start'])->toBe(2)
        ->and($index['chunks'][2]['source_kind'])->toBe('appendix')
        ->and($index['chunks'][2]['section'])->toBe('REQUEST FOR FUNDING')
        ->and($index['chunks'][2]['page_start'])->toBe(2)
        ->and($index['chunks'][2]['page_end'])->toBe(3);
})->with(['APPENDICES', 'ATTACHMENTS']);

test('manual command reports statistics and can force a repeatable local import', function () {
    Process::fake([Process::result(output: "1. Research Policy\n\nWritten approval is required.\f")]);

    $this->artisan('research-manual:index', ['path' => $this->manualPdf])
        ->expectsOutput('Indexed: Approved Research Manual (2023)')
        ->expectsOutput('1 PDF pages; 1 searchable passages.')
        ->assertSuccessful();
    $this->artisan('research-manual:index', ['path' => $this->manualPdf])
        ->expectsOutput('Already indexed: Approved Research Manual (2023)')
        ->assertSuccessful();
    $this->artisan('research-manual:index', ['path' => $this->manualPdf, '--force' => true])
        ->expectsOutput('Indexed: Approved Research Manual (2023)')
        ->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command[0] === 'test-pdftotext', 2);
});
