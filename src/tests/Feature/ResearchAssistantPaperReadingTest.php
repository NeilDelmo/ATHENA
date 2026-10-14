<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantPaperReader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Http::preventStrayRequests();
    $this->paperReaderUser = User::factory()->create();
    $this->paperReaderUser->assignRole('faculty');
    $this->actingAs($this->paperReaderUser);
    $this->paperReaderDraft = ProposalDraft::create([
        'user_id' => $this->paperReaderUser->id,
        'project_title' => 'Shoreline field study',
        'project_leader' => 'Research leader',
        'duration_months' => 6,
        'planned_start' => '2026-01-01',
        'planned_end' => '2026-06-30',
    ]);
});

function savedPaperPacket(array $result): array
{
    $prompt = $result['prompt'];

    return json_decode(substr($prompt, strpos($prompt, '{')), true, flags: JSON_THROW_ON_ERROR);
}

function saveReaderPaper(ProposalDraft $draft, string $type, array $data): void
{
    $draft->documents()->create(['document_type' => $type, 'position' => 0, 'source_data' => $data]);
    $draft->unsetRelation('documents');
}

test('saved paper questions read matching pre PDF fields without a current paper slug', function () {
    saveReaderPaper($this->paperReaderDraft, 'work_plan', ['entries' => [['objective' => 'Old objective', 'activity' => 'Monthly shoreline transects', 'months' => [2, 4, 6]]]]);
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['specific_objectives' => [['description' => 'Measure shoreline recovery']]]);
    $result = app(ResearchAssistantPaperReader::class)->read($this->paperReaderUser, $this->paperReaderDraft, null, 'What activities and months are in my work plan?');
    expect($result['prompt'])->toContain('Monthly shoreline transects', 'Measure shoreline recovery')
        ->not->toContain('Old objective')
        ->and(collect($result['sources'])->pluck('reference'))->toContain('Attachment A: Work Plan')
        ->and(savedPaperPacket($result)['record_basis'])->toBe('private_saved_draft');
    Http::assertNothingSent();
});

test('draft paper reading computes expense and synchronized budget totals without modifying documents', function () {
    $expense = ['items' => [['category' => 'mooe', 'account' => 'Travelling Expenses', 'sub_account' => 'Local', 'particulars' => 'Field transport', 'quantity' => 3, 'unit_cost' => 450]]];
    saveReaderPaper($this->paperReaderDraft, 'expense_breakdown', $expense);
    saveReaderPaper($this->paperReaderDraft, 'line_item_budget', ['amounts' => ['travel_local' => 99999]]);
    $reader = app(ResearchAssistantPaperReader::class);
    $expenseResult = $reader->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'expense-breakdown', 'What is the grand total and transport item total cost?');
    expect($expenseResult['prompt'])->toContain('1350', 'Quantity: 3', 'Unit Cost: 450', 'Total Cost: 1350');
    $documents = $this->paperReaderDraft->documents;
    $effective = $reader->effectiveData($this->paperReaderDraft, 'line_item_budget', ['amounts' => ['travel_local' => 99999]], $documents);
    expect($effective['computed_project_total'])->toBe(1350.0)
        ->and($this->paperReaderDraft->documents()->where('document_type', 'expense_breakdown')->first()->source_data)->toEqual($expense);
});

test('query selection finds a late narrative paragraph and late table row within the prompt byte budget', function () {
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['methodology' => ['data_analysis' => str_repeat('Routine descriptive measurements. ', 450).'\nKendall seasonal adjustment handles tidal autocorrelation.']]);
    $result = app(ResearchAssistantPaperReader::class)->read($this->paperReaderUser, $this->paperReaderDraft, null, 'What does my methodology say about Kendall seasonal adjustment?');
    expect($result['prompt'])->toContain('Kendall seasonal adjustment handles tidal autocorrelation')
        ->and(strlen($result['prompt']))->toBeLessThanOrEqual(12000)
        ->and(savedPaperPacket($result)['truncated'])->toBeTrue();

    saveReaderPaper($this->paperReaderDraft, 'expense_breakdown', ['items' => collect(range(1, 90))->map(fn (int $index): array => ['category' => 'mooe', 'account' => 'Supplies', 'particulars' => $index === 89 ? 'Zirconium calibration probe' : 'Ordinary supply '.$index, 'quantity' => 2, 'unit_cost' => 10])->all()]);
    $result = app(ResearchAssistantPaperReader::class)->read($this->paperReaderUser, $this->paperReaderDraft, null, 'What is the Zirconium calibration probe expense?');
    expect($result['prompt'])->toContain('Zirconium calibration probe')
        ->and(strlen($result['prompt']))->toBeLessThanOrEqual(12000);
});

test('every catalog paper can be selected before PDF generation including blank automatic forms', function () {
    foreach (['detailed_proposal', 'work_plan', 'line_item_budget', 'expense_breakdown'] as $type) {
        saveReaderPaper($this->paperReaderDraft, $type, ['project_title' => 'Shoreline field study']);
    }
    saveReaderPaper($this->paperReaderDraft, 'curriculum_vitae', ['people' => [['academic_background' => [['degree' => 'Marine science doctorate']]]]]);
    $reader = app(ResearchAssistantPaperReader::class);
    foreach (array_keys(config('proposal_papers')) as $slug) {
        $result = $reader->readPaper($this->paperReaderUser, $this->paperReaderDraft, $slug, 'Summarize this paper.');
        expect($result['coverage'])->toHaveCount(1)
            ->and($result['coverage'][0]['paper_slug'])->toBe($slug)
            ->and($result['prompt'])->not->toBeEmpty();
    }
    $gad = $reader->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'gad-checklist', 'What is the GAD score?');
    expect($gad['prompt'])->toContain('Generated blank GAD checklist', 'scores, and signatures are not supplied')
        ->and($gad['coverage'][0]['status'])->toBe('generated_from_saved_details');
    $screening = $reader->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'initial-screening-form', 'What does my initial screening form contain?');
    expect($screening['prompt'])->toContain('first_submission', 'Generated blank initial screening form');
});

test('CV reading exposes relevant qualifications but excludes sensitive and unrelated person fields', function () {
    saveReaderPaper($this->paperReaderDraft, 'curriculum_vitae', ['people' => [[
        'birthday' => '1990-04-02', 'street' => 'Secret house', 'gender' => 'Private gender', 'email' => 'private@example.test', 'cellphone' => '09171234567',
        'academic_background' => [['degree' => 'Doctorate in coastal ecology', 'major_field' => 'Habitat restoration']],
        'publications' => [['title' => 'Shoreline monitoring methods']],
    ]]]);
    $reader = app(ResearchAssistantPaperReader::class);
    $generic = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'Explain my proposal objectives.');
    expect($generic['prompt'])->not->toContain('Doctorate in coastal ecology');
    $cv = $reader->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'curriculum-vitae', 'What qualifications and publications are in the CV?');
    expect($cv['prompt'])->toContain('Doctorate in coastal ecology', 'Shoreline monitoring methods')
        ->not->toContain('1990-04-02', 'Secret house', 'Private gender', 'private@example.test', '09171234567');
});

test('historical submitted fields remain unchanged when current draft data changes', function () {
    $topic = TopicProposal::create(['user_id' => $this->paperReaderUser->id, 'research_category_id' => ResearchCategory::create(['name' => 'Reader category '.uniqid()])->id, 'title' => 'Submitted shoreline study', 'description' => 'Historical description', 'status' => 'pending']);
    $version = $topic->versions()->create(['version_number' => 2, 'submitted_by' => $this->paperReaderUser->id, 'submission_type' => 'revision', 'title' => $topic->title, 'description' => 'Historical version', 'file_path' => 'missing.pdf', 'original_filename' => 'missing.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1]);
    $version->files()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'missing-plan.pdf', 'original_filename' => 'missing-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'source_data' => ['entries' => [['objective' => 'Historical habitat objective', 'activity' => 'Historical transects', 'months' => [3]]]]]);
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['specific_objectives' => [['description' => 'Changed modern objective']]]);
    $result = app(ResearchAssistantPaperReader::class)->readPaper($this->paperReaderUser, $topic, 'work-plan', 'What objective and activity were submitted?');
    expect($result['prompt'])->toContain('Historical habitat objective', 'Historical transects')
        ->not->toContain('Changed modern objective')
        ->and(savedPaperPacket($result)['record_basis'])->toBe('latest_submitted_version')
        ->and(savedPaperPacket($result)['version_number'])->toBe(2);
});

test('paper reading rejects an unrelated faculty account even with a known model ID', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty');
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['rationale' => 'Private rationale']);
    app(ResearchAssistantPaperReader::class)->readPaper($other, $this->paperReaderDraft, 'detailed-proposal', 'Read this paper.');
})->throws(AuthorizationException::class);

test('an accepted shared draft member can read while revoked membership is rechecked', function () {
    $member = User::factory()->create();
    $member->assignRole('faculty');
    $membership = $this->paperReaderDraft->members()->create(['user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'accepted_at' => now()]);
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['rationale' => 'Shared habitat monitoring rationale.']);
    $reader = app(ResearchAssistantPaperReader::class);
    expect($reader->readPaper($member, $this->paperReaderDraft, 'detailed-proposal', 'Explain the rationale.')['prompt'])->toContain('Shared habitat monitoring rationale.');
    $membership->update(['accepted_at' => null]);
    expect(fn () => $reader->readPaper($member, $this->paperReaderDraft, 'detailed-proposal', 'Explain the rationale.'))->toThrow(AuthorizationException::class);
});

test('missing saved fields identify uploaded unread content instead of claiming the paper was read', function () {
    $this->paperReaderDraft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'source_data' => null, 'file_path' => 'not-read.pdf']);
    $result = app(ResearchAssistantPaperReader::class)->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'work-plan', 'Summarize this paper.');
    expect($result['coverage'][0]['status'])->toBe('uploaded_content_not_read')
        ->and($result['coverage'][0]['selected_passages'])->toBe(0)
        ->and($result['sources'])->toBe([]);
});

test('manual only questions do not add saved proposal content and paper followups retain context', function () {
    saveReaderPaper($this->paperReaderDraft, 'work_plan', ['entries' => [['activity' => 'Kelp verification in month six', 'months' => [6]]]]);
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['specific_objectives' => [['description' => 'Kelp verification']]]);
    $reader = app(ResearchAssistantPaperReader::class);
    expect($reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'According to the manual what is the RRL minimum?')['prompt'])->toBeNull()
        ->and($reader->wantsPaperRead('Explain the reviewer comments.'))->toBeFalse();
    $followup = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'And what about that?', [], [['role' => 'user', 'content' => 'What is in my work plan for kelp verification?']]);
    expect($followup['prompt'])->toContain('Kelp verification');
});

test('specific unmatched questions report missing passages instead of unrelated rows as evidence', function () {
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['rationale' => 'Shoreline habitat observations.']);
    $result = app(ResearchAssistantPaperReader::class)->readPaper($this->paperReaderUser, $this->paperReaderDraft, 'detailed-proposal', 'Find zephyrium isotope spectroscopy.');
    $packet = savedPaperPacket($result);
    expect($packet['no_match'])->toBeTrue()->and($packet['passages'])->toBe([])->and($result['sources'])->toBe([]);
});

test('vague field help selects the focused late saved row but an explicit other paper question wins', function () {
    saveReaderPaper($this->paperReaderDraft, 'expense_breakdown', ['items' => collect(range(0, 80))->map(fn (int $index): array => ['category' => 'mooe', 'account' => 'Supplies', 'particulars' => $index === 77 ? 'Focused spectrometer calibration' : 'Ordinary supply '.$index, 'quantity' => 1, 'unit_cost' => 100])->all()]);
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['rationale' => 'Detailed rationale only.']);
    $reader = app(ResearchAssistantPaperReader::class);
    $focused = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'How should I improve this?', ['paper_slug' => 'expense-breakdown', 'field' => 'items[77][purpose]']);
    $packet = savedPaperPacket($focused);
    expect($focused['prompt'])->toContain('Focused spectrometer calibration')
        ->and($packet['selection_scope'])->toBe('current_saved_field')
        ->and(array_column($packet['passages'], 'field'))->toBe(['items.77']);
    $explicit = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'How should I improve the spectrometer calibration expense?', ['paper_slug' => 'detailed-proposal', 'field' => 'rationale']);
    expect($explicit['prompt'])->toContain('Focused spectrometer calibration')
        ->and(savedPaperPacket($explicit)['selection_scope'])->toBe('relevant_saved_paper_passages');
});

test('selected submitted file reading stays bound to its historical version after a newer submission', function () {
    $topic = TopicProposal::create(['user_id' => $this->paperReaderUser->id, 'research_category_id' => ResearchCategory::create(['name' => 'Bound reader category '.uniqid()])->id, 'title' => 'Historic file study', 'description' => 'Historical description', 'status' => 'pending']);
    $files = [];
    foreach ([1 => 'Original archive hypothesis', 2 => 'Newer replacement hypothesis'] as $number => $rationale) {
        $version = $topic->versions()->create(['version_number' => $number, 'submitted_by' => $this->paperReaderUser->id, 'submission_type' => $number === 1 ? 'initial' : 'revision', 'title' => $topic->title, 'description' => 'Version description', 'file_path' => 'missing.pdf', 'original_filename' => 'missing.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1]);
        $files[] = $version->files()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'file_path' => 'missing-paper.pdf', 'original_filename' => 'missing-paper.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'source_data' => ['rationale' => $rationale]]);
    }
    $result = app(ResearchAssistantPaperReader::class)->readVersionPaper($this->paperReaderUser, $files[0], 'Explain the rationale.');
    $packet = savedPaperPacket($result);
    expect($result['prompt'])->toContain('Original archive hypothesis')->not->toContain('Newer replacement hypothesis')
        ->and($packet['version_number'])->toBe(1)
        ->and($packet['record_basis'])->toBe('selected_submitted_version')
        ->and($result['sources'][0]['url'])->toContain('/versions/'.$files[0]->proposal_version_id.'/');
    $secondSlot = $files[0]->version->files()->create(['document_type' => 'detailed_proposal', 'position' => 1,
        'file_path' => 'second-paper.pdf', 'original_filename' => 'second-paper.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1,
        'source_data' => ['rationale' => 'Second slot has its own rationale.']]);
    $selected = app(ResearchAssistantPaperReader::class)->readVersionPaper($this->paperReaderUser, $secondSlot, 'Explain the rationale.');
    expect($selected['prompt'])->toContain('Second slot has its own rationale.')->not->toContain('Original archive hypothesis')
        ->and(array_column(savedPaperPacket($selected)['passages'], 'document_id'))->toBe([$secondSlot->id]);
});

test('submitted generated totals use only immutable same version values and preserve supplied totals', function () {
    $topic = TopicProposal::create(['user_id' => $this->paperReaderUser->id, 'research_category_id' => ResearchCategory::create(['name' => 'Submitted calculation '.uniqid()])->id, 'title' => 'Historical expense study', 'description' => 'Historical expense description', 'status' => 'pending']);
    $version = $topic->versions()->create(['version_number' => 1, 'submitted_by' => $this->paperReaderUser->id, 'submission_type' => 'initial', 'title' => $topic->title, 'description' => 'Original submission', 'file_path' => 'missing.pdf', 'original_filename' => 'missing.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1]);
    $files = [];
    foreach (['expense_breakdown' => ['items' => [['category' => 'mooe', 'account' => 'Travelling Expenses', 'sub_account' => 'Local', 'particulars' => 'Historical field transport', 'quantity' => 2, 'unit_cost' => 450]]],
        'line_item_budget' => ['mooe_total' => 900, 'co_total' => 1250, 'level_of_call' => 'constituent_campus'],
        'detailed_proposal' => ['rationale' => 'Original rationale', 'co_total' => 222],
    ] as $type => $source) {
        $files[$type] = $version->files()->create(['document_type' => $type, 'position' => 0, 'file_path' => 'missing-'.$type.'.pdf', 'original_filename' => 'missing-'.$type.'.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'source_data' => $source]);
    }
    saveReaderPaper($this->paperReaderDraft, 'expense_breakdown', ['items' => [['category' => 'mooe', 'account' => 'Supplies', 'particulars' => 'Modern unrelated equipment', 'quantity' => 100, 'unit_cost' => 999]]]);
    saveReaderPaper($this->paperReaderDraft, 'line_item_budget', ['mooe_total' => 99900, 'co_total' => 88800]);
    $reader = app(ResearchAssistantPaperReader::class);
    $expense = $reader->readVersionPaper($this->paperReaderUser, $files['expense_breakdown'], 'What is the grand total and historical transport total cost?');
    expect($expense['prompt'])->toContain('Total Cost: 900', '"field":"grand_total"', '"text":"900"')
        ->not->toContain('Modern unrelated equipment', '99900');
    $detail = $reader->readVersionPaper($this->paperReaderUser, $files['detailed_proposal'], 'What are the MOOE and CO budget totals?');
    expect($detail['prompt'])->toContain('"field":"mooe_total"', '"text":"900"', '"field":"co_total"', '"text":"222"')
        ->not->toContain('"text":"1250"', '88800');
    expect($files['expense_breakdown']->fresh()->source_data)->not->toHaveKey('grand_total')
        ->and($files['detailed_proposal']->fresh()->source_data)->not->toHaveKey('mooe_total');
    $preserved = $reader->effectiveSubmittedData('expense_breakdown', ['items' => [['quantity' => 2, 'unit_cost' => 450, 'total_cost' => 777]], 'grand_total' => 888], collect());
    expect($preserved['items'][0]['total_cost'])->toBe(777)->and($preserved['grand_total'])->toBe(888);
});

test('ordinary participant and total cost questions read the selected proposal without a paper slug', function () {
    saveReaderPaper($this->paperReaderDraft, 'detailed_proposal', ['methodology' => ['research_design' => 'A stratified sample includes 120 participants from three shoreline communities.']]);
    saveReaderPaper($this->paperReaderDraft, 'expense_breakdown', ['items' => [['category' => 'mooe', 'account' => 'Supplies', 'particulars' => 'Field logs', 'quantity' => 12, 'unit_cost' => 250]]]);
    $reader = app(ResearchAssistantPaperReader::class);
    $participants = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'How many participants are in my study?');
    expect($participants['prompt'])->toContain('120 participants');
    $cost = $reader->read($this->paperReaderUser, $this->paperReaderDraft, null, 'What is my total cost?');
    expect($cost['prompt'])->toContain('Total Cost: 3000');
    foreach (['What is my research design?', 'How long is the timeline and duration?', 'What data analysis is planned?', 'What are the expected results?'] as $question) {
        expect($reader->wantsPaperRead($question))->toBeTrue();
    }
});
