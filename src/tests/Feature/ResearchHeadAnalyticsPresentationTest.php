<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Elena Santos', 'college' => 'College of Science']);
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create(['title' => 'Research analytics', 'academic_year' => '2026-2027', 'opens_at' => '2026-08-01', 'closes_at' => '2026-10-31', 'status' => 'open']);
    $this->project = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => fake()->unique()->sentence(), 'status' => 'approved',
        'notice_to_proceed_issued_at' => '2026-08-01', 'project_status' => 'ongoing',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-08-01', 'approved_end_date' => '2027-07-31'],
        'estimated_duration_months' => 12, 'estimated_budget' => 100000, ...$attributes,
    ]);
});

test('analytics summary uses five explicit drilldown controls instead of generic destinations', function () {
    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-dashboard-kpi-band]/button'))->toHaveCount(5)
        ->and($xpath->query('//*[@data-dashboard-kpi-band]//a'))->toHaveCount(0);
    $component->assertSeeHtml('wire:click="showReviewQueue"')
        ->assertSeeHtml('wire:click="showFacultyResearchers"')
        ->assertSee('Current status of the selected submission cohort')
        ->assertDontSee('View user directory');
});

test('delayed drilldown precedes charts and explains the exact matching project', function () {
    $delayed = ($this->project)(['title' => 'Coastal water quality', 'notice_to_proceed_data' => ['approved_start_date' => '2026-08-01', 'approved_end_date' => '2026-09-15']]);
    ($this->project)(['title' => 'Future scheduled study']);
    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->set('academicYear', '2026-2027')->call('showProjects', 'delayed');

    $component->assertSee('Delayed / overdue projects')->assertSee('1 matching project')
        ->assertSee('Project deadline / reported milestone passed')
        ->assertSee('14 days overdue')->assertSee('Inspect monitoring');

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    $results = $xpath->query('//*[@id="active-projects"]')->item(0);
    expect($xpath->query('//*[@id="active-projects"]/preceding-sibling::*[@data-dashboard-kpi-band]'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="active-projects"]/following-sibling::*//*[@id="monthly-trend-heading"]'))->toHaveCount(1);
    expect($results->textContent)->toContain('Coastal water quality')->not->toContain('Future scheduled study');
    expect($xpath->query('//*[@id="active-projects"]//a[@href="'.route('topics.show', $delayed).'#project-monitoring"]')->length)->toBeGreaterThan(0);

    if (getenv('ATHENA_EXPORT_ANALYTICS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/analytics-delayed.html'), $component->html());
    }
});

test('faculty drilldown identifies counted researchers and their active projects', function () {
    ($this->project)(['title' => 'Campus biodiversity survey']);
    ($this->project)(['title' => 'Archived completed study', 'project_status' => 'completed']);
    $unrelated = User::factory()->create(['name' => 'Directory-only researcher']);
    $unrelated->assignRole('faculty');
    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->call('showFacultyResearchers');

    $component->assertSee('Faculty in active research')->assertSee('1 matching researcher')
        ->assertSee('Elena Santos')->assertSee('College of Science')
        ->assertSee('Campus biodiversity survey')->assertSee('Project lead');
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    $results = $xpath->query('//*[@id="faculty-researchers"]')->item(0);
    expect($results->textContent)->not->toContain('Directory-only researcher')->not->toContain('Archived completed study');

    if (getenv('ATHENA_EXPORT_ANALYTICS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/analytics-faculty.html'), $component->html());
    }
});

test('budget display distinguishes a missing denominator from an absent report', function () {
    $project = ($this->project)(['estimated_budget' => null]);
    ProjectProgressReport::create([
        'topic_id' => $project->id, 'submitted_by' => $this->faculty->id,
        'reporting_date' => '2026-09-20', 'progress_percentage' => 25, 'accomplishments' => 'Sampling completed',
        'review_status' => 'reviewed', 'budget_utilization' => [['actual_amount' => 4000]],
    ]);

    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->assertSee('Budget not recorded; utilization unavailable')
        ->assertSee('Report date: 2026-09-20');
});

test('analytics drilldowns return matching records and accessible result targets for browser interactions', function () {
    $proposal = TopicProposal::create(['user_id' => $this->faculty->id, 'research_call_id' => $this->call->id, 'title' => 'Mangrove restoration proposal', 'status' => 'pending']);
    $version = $proposal->versions()->make(['submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial', 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'title' => $proposal->title]);
    $version->created_at = '2026-09-05';
    $version->save();
    ($this->project)(['title' => 'Coastal water quality', 'notice_to_proceed_data' => ['approved_start_date' => '2026-08-01', 'approved_end_date' => '2026-09-15']]);
    ($this->project)(['title' => 'Campus biodiversity survey']);
    ($this->project)(['title' => 'Archived completed study', 'project_status' => 'completed']);

    $cases = [
        ['label' => 'review', 'method' => 'showReviewQueue', 'params' => [], 'target' => 'received-proposals', 'heading' => 'Proposals awaiting your review', 'records' => ['Mangrove restoration proposal']],
        ['label' => 'active', 'method' => 'showProjects', 'params' => ['active'], 'target' => 'active-projects', 'heading' => 'Active projects', 'records' => ['Coastal water quality', 'Campus biodiversity survey']],
        ['label' => 'delayed', 'method' => 'showProjects', 'params' => ['delayed'], 'target' => 'active-projects', 'heading' => 'Delayed / overdue projects', 'records' => ['Coastal water quality']],
        ['label' => 'completed', 'method' => 'showProjects', 'params' => ['completed'], 'target' => 'active-projects', 'heading' => 'Completed projects', 'records' => ['Archived completed study']],
        ['label' => 'faculty', 'method' => 'showFacultyResearchers', 'params' => [], 'target' => 'faculty-researchers', 'heading' => 'Faculty in active research', 'records' => ['Elena Santos', 'Coastal water quality', 'Campus biodiversity survey']],
        ['label' => 'month', 'method' => 'showSubmissionMonth', 'params' => ['2026-09'], 'target' => 'received-proposals', 'heading' => 'Proposals with submissions in Sep 2026', 'records' => ['Mangrove restoration proposal']],
        ['label' => 'pipeline', 'method' => 'setPipeline', 'params' => ['submitted'], 'target' => 'received-proposals', 'heading' => 'Submitted proposals', 'records' => ['Mangrove restoration proposal']],
        ['label' => 'distribution', 'method' => 'showProjects', 'params' => ['ongoing'], 'target' => 'active-projects', 'heading' => 'Ongoing projects', 'records' => ['Campus biodiversity survey']],
    ];
    $fixtures = [];
    foreach ($cases as $case) {
        $component = Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027', 'fromDate' => '2026-08-01', 'toDate' => '2026-09-30'])->test(ResearchHeadDashboard::class);
        $initial = $component->html();
        $component->call($case['method'], ...$case['params'])->assertDispatched('analytics-drilldown', target: $case['target']);
        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $xpath = new DOMXPath($document);
        $target = $xpath->query('//*[@id="'.$case['target'].'"]')->item(0);
        expect($target)->not->toBeNull();
        expect($target->getAttribute('tabindex'))->toBe('-1');
        foreach ([$case['heading'], ...$case['records']] as $text) {
            expect($target->textContent)->toContain($text);
        }
        $fixtures[] = [...$case, 'initial' => $initial, 'response' => ['components' => [[
            'snapshot' => json_encode($component->snapshot), 'effects' => $component->effects,
        ]]]];
        if ($case['label'] === 'faculty') {
            $component->set('showFaculty', false)->assertDontSeeHtml('id="faculty-researchers"');
            $fixtures[array_key_last($fixtures)]['close_response'] = ['components' => [[
                'snapshot' => json_encode($component->snapshot), 'effects' => $component->effects,
            ]]];
        }
    }
    if (getenv('ATHENA_EXPORT_ANALYTICS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/analytics-interactions.json'), json_encode($fixtures, JSON_UNESCAPED_UNICODE));
    }
});
