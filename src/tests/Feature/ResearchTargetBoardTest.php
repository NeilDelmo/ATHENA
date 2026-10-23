<?php

use App\Livewire\ResearchHeadDashboard;
use App\Livewire\ResearchTargetBoard;
use App\Models\ResearchAnnualTarget;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    $this->withoutVite();

    foreach (['faculty', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->editor = function (string $role = 'research_head'): User {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $role]);

        return $user;
    };
    $this->targetForm = [
        'academic_year' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'indicators' => [
            'publications' => ['total' => 6, 'allocations' => ['CAS' => 2, 'CICS' => 4]],
            'students_ylp' => ['total' => 3, 'allocations' => ['CAS' => 0]],
            'funded_collaborations' => ['total' => '', 'allocations' => []],
            'external_funding' => ['total' => 2, 'allocations' => []],
            'completed_research' => ['total' => 4, 'allocations' => []],
            'approved_research' => ['total' => 5, 'allocations' => []],
            'utilized_outputs' => ['total' => 1, 'allocations' => []],
            'faculty_engaged' => ['total' => 75.5, 'allocations' => ['CAS' => 60, 'CICS' => 80.5]],
            'new_faculty' => ['total' => 0, 'allocations' => []],
            'faculty_osi' => ['total' => 2, 'allocations' => []],
            'industry_collaborations' => ['total' => 1, 'allocations' => []],
        ],
    ];
});

test('the target editor offers every college in a dropdown for each indicator', function () {
    ($this->editor)();

    $component = Livewire::test(ResearchTargetBoard::class)
        ->call('editTargets', true)
        ->set('targetForm', $this->targetForm);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);

    foreach (array_keys(ResearchAnnualTarget::INDICATORS) as $key) {
        $selector = $xpath->query('//select[@id="research-target-'.$key.'-college"]');
        expect($selector->length)->toBe(1);
        $options = $xpath->query('option', $selector->item(0));
        expect(array_map(fn (DOMElement $option): string => $option->getAttribute('value'), iterator_to_array($options)))
            ->toEqualCanonicalizing(['CICS', 'CTE', 'CABEIHM', 'CCJE', 'CAS', 'CHS', 'LAB_SCHOOL']);
        foreach (['CICS', 'CTE', 'CABEIHM', 'CCJE', 'CAS', 'CHS', 'LAB_SCHOOL'] as $code) {
            $input = $xpath->query('//input[@id="research-target-'.$key.'-'.$code.'"]')->item(0);
            expect($input->getAttribute('x-bind:disabled'))->toBe("selectedCollege !== '".$code."'");
        }
    }

    $component->call('saveTargets')->assertHasNoErrors();
    expect(ResearchAnnualTarget::sole()->indicators['publications']['allocations'])
        ->toBe(['CAS' => 2, 'CICS' => 4]);
});

test('head and secretary can record every research indicator with college allocations', function (string $role) {
    $user = ($this->editor)($role);

    $component = Livewire::test(ResearchTargetBoard::class)
        ->call('editTargets', true)
        ->set('targetForm', $this->targetForm)
        ->call('saveTargets')
        ->assertHasNoErrors()
        ->assertSet('year', '2026-2027')
        ->assertSet('targetForm', [])
        ->assertSet('editingTargets', false)
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 11
            && $rows['publications']['total'] === 6
            && $rows['publications']['allocations']['CAS'] === 2
            && $rows['faculty_engaged']['unit'] === 'percent'
            && $rows['faculty_engaged']['total'] == 75.5);

    $target = ResearchAnnualTarget::sole();
    expect($target->updated_by)->toBe($user->id)
        ->and($target->publications_target)->toBe(6)
        ->and($target->indicators['students_ylp']['allocations']['CAS'])->toBe(0)
        ->and($target->indicators['funded_collaborations']['total'])->toBeNull()
        ->and($target->indicators['new_faculty']['total'])->toBe(0)
        ->and($target->indicators['faculty_engaged']['allocations']['CICS'])->toEqual(80.5);

    if (getenv('ATHENA_CAPTURE_TARGETS') === '1' && $role === 'research_head') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/target-board-office.html'), $component->html());
        File::put(storage_path('framework/testing/target-board-page.html'), $this->get(route('research_head.targets.index'))->assertSuccessful()->getContent());
        $component->call('editTargets');
        File::put(storage_path('framework/testing/target-board-editor.html'), $component->html());
    }
})->with(['research_head', 'research_secretary']);

test('the target plan overview counts configured indicators and unique assigned colleges including zero', function () {
    ($this->editor)();
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'publications_target' => 0,
        'indicators' => [
            'publications' => ['allocations' => ['CAS' => 0]],
            'students_ylp' => ['total' => null, 'allocations' => ['CICS' => null]],
            'faculty_engaged' => ['total' => 75.5, 'allocations' => ['CAS' => 60.5, 'CHS' => 0]],
        ],
    ]);

    $component = Livewire::test(ResearchTargetBoard::class)->assertSeeHtml('data-research-target-board');
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//dl[@data-target-plan-overview]')->length)->toBe(1);
    foreach (['indicators' => '11', 'configured' => '2', 'colleges' => '2'] as $stat => $value) {
        expect(trim($xpath->query('//*[@data-target-plan-stat="'.$stat.'"]/dd')->item(0)?->textContent ?? ''))->toBe($value);
    }

    expect($xpath->query('//tbody/tr[@data-target-indicator]')->length)->toBe(11)
        ->and(trim($xpath->query('//tr[@data-target-indicator="publications"]//*[@data-target-overall]')->item(0)?->textContent ?? ''))->toBe('0')
        ->and(trim($xpath->query('//tr[@data-target-indicator="students_ylp"]//*[@data-target-overall]')->item(0)?->textContent ?? ''))->toBe('Not set')
        ->and(trim($xpath->query('//tr[@data-target-indicator="faculty_engaged"]//*[@data-target-overall]')->item(0)?->textContent ?? ''))->toBe('75.5%');
});

test('existing targets use the legacy publication total and leave unrelated legacy targets unchanged', function () {
    ($this->editor)();
    $target = ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'projects_target' => 41, 'faculty_target' => 53, 'publications_target' => 7,
        'indicators' => ['publications' => ['total' => 999, 'allocations' => ['CAS' => 2]]],
    ]);

    Livewire::test(ResearchTargetBoard::class)
        ->assertViewHas('rows', fn (array $rows): bool => $rows['publications']['total'] === 7)
        ->call('editTargets')
        ->assertSet('targetForm.indicators.publications.total', 7)
        ->assertSet('targetForm.indicators.publications.allocations.CAS', 2)
        ->assertSet('targetForm.indicators.students_ylp.total', '')
        ->assertSet('targetForm.indicators.students_ylp.allocations.CICS', '')
        ->set('targetForm.indicators.publications.total', 9)
        ->call('saveTargets')->assertHasNoErrors();

    expect($target->refresh()->publications_target)->toBe(9)
        ->and($target->projects_target)->toBe(41)
        ->and($target->faculty_target)->toBe(53)
        ->and(ResearchAnnualTarget::count())->toBe(1);
});

test('research targets default to the current period before a later scheduled year', function () {
    ($this->editor)();
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    ResearchAnnualTarget::factory()->create(['academic_year' => '2027-2028', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31']);

    Livewire::test(ResearchTargetBoard::class)->assertSet('year', '2026-2027');
});

test('the dashboard target summary shows saved goals including zero and percentage targets across years', function () {
    ($this->editor)();
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'publications_target' => 6,
        'indicators' => ['students_ylp' => ['total' => 0], 'faculty_engaged' => ['total' => 75.5]],
    ]);
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31',
        'publications_target' => 12,
    ]);

    $component = Livewire::test(ResearchTargetBoard::class, ['compact' => true])
        ->assertSet('year', '2026-2027')
        ->assertSeeHtml('data-research-target-board')
        ->assertSeeHtml('data-dashboard-target-summary')
        ->assertDontSeeHtml('data-target-plan-overview')
        ->assertDontSeeHtml('wire:click="editTargets"')
        ->assertSee(route('research_head.targets.index'));
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    foreach (['publications' => '6', 'students_ylp' => '0', 'faculty_engaged' => '75.5%', 'funded_collaborations' => 'Not set'] as $key => $value) {
        expect(trim($xpath->query('//*[@data-target-indicator="'.$key.'"]/dd')->item(0)?->textContent ?? ''))->toBe($value);
    }

    if (getenv('ATHENA_CAPTURE_TARGETS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/target-board-compact.html'), $component->html());
    }

    $component->set('year', '2025-2026')
        ->assertViewHas('rows', fn (array $rows): bool => $rows['publications']['total'] === 12)
        ->assertSet('editingTargets', false);
});

test('the dashboard target summary provides an empty state and a way to set targets', function () {
    ($this->editor)();

    $component = Livewire::test(ResearchTargetBoard::class, ['compact' => true])
        ->assertSeeHtml('data-dashboard-target-summary')
        ->assertDontSeeHtml('data-target-plan-overview')
        ->assertSee('No research targets have been set for 2026.')
        ->assertSee(route('research_head.targets.index'))
        ->assertDontSeeHtml('wire:click="editTargets"');

    if (getenv('ATHENA_CAPTURE_TARGETS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/target-board-compact-empty.html'), $component->html());
        File::put(storage_path('framework/testing/target-board-empty.html'), Livewire::test(ResearchTargetBoard::class)->html());
    }
});

test('the legacy editor cannot lower or clear a publication total below saved college allocations', function (mixed $total) {
    ($this->editor)();
    $allocations = ['CAS' => 2, 'CICS' => 4];
    $target = ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'publications_target' => 6,
        'indicators' => ['publications' => ['allocations' => $allocations]],
    ]);

    Livewire::withQueryParams(['academicYear' => '2026-2027'])
        ->test(ResearchHeadDashboard::class, ['overview' => false])
        ->call('editTargets')->set('targetForm.publications_target', $total)
        ->call('saveTargets')->assertHasErrors('targetForm.publications_target');

    expect($target->refresh()->publications_target)->toBe(6)
        ->and($target->indicators['publications']['allocations'])->toBe($allocations);
})->with(['lower total' => [3], 'blank total' => ['']]);

test('research targets fall back to the latest saved period or current calendar year', function () {
    ($this->editor)();
    Livewire::test(ResearchTargetBoard::class)->assertSet('year', '2026');
    ResearchAnnualTarget::factory()->create(['academic_year' => '2024-2025', 'starts_on' => '2024-08-01', 'ends_on' => '2025-07-31']);
    ResearchAnnualTarget::factory()->create(['academic_year' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31']);

    Livewire::test(ResearchTargetBoard::class)->assertSet('year', '2025-2026');
});

test('switching years and cancelling clear the editor without saving changes', function () {
    ($this->editor)();
    $current = ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'publications_target' => 7]);
    ResearchAnnualTarget::factory()->create(['academic_year' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31', 'publications_target' => 3]);

    Livewire::test(ResearchTargetBoard::class)
        ->call('editTargets')->set('targetForm.indicators.publications.total', 99)
        ->set('year', '2025-2026')->assertSet('editingTargets', false)->assertSet('targetForm', [])
        ->assertViewHas('rows', fn (array $rows): bool => $rows['publications']['total'] === 3)
        ->call('editTargets')->assertSet('targetForm.indicators.publications.total', 3)
        ->call('cancelEditing')->assertSet('editingTargets', false)->assertSet('targetForm', []);

    expect($current->refresh()->publications_target)->toBe(7);
});

test('a coordinator receives only their own college targets in HTML view data and public state', function () {
    $coordinator = User::factory()->create(['college' => User::COLLEGES['CAS']]);
    $coordinator->assignRole('research_coordinator');
    $this->actingAs($coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'publications_target' => 876543,
        'indicators' => [
            'publications' => ['allocations' => ['CAS' => 12, 'CICS' => 765432]],
            'faculty_engaged' => ['total' => 94.25, 'allocations' => ['CAS' => 60.5, 'CICS' => 83.25]],
        ],
    ]);

    $component = Livewire::test(ResearchTargetBoard::class)
        ->assertSet('targetForm', [])
        ->assertDontSeeHtml('data-target-plan-overview')
        ->assertViewHas('rows', function (array $rows): bool {
            foreach ($rows as $row) {
                expect(array_keys($row))->toEqualCanonicalizing(['indicator', 'label', 'unit', 'value']);
            }

            return $rows['publications']['value'] === 12 && $rows['faculty_engaged']['value'] == 60.5;
        });

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))->not->toContain('876543', '765432', '94.25', '83.25')
        ->and($component->html())->not->toContain('876543', '765432', '94.25', '83.25');

    if (getenv('ATHENA_CAPTURE_TARGETS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/target-board-coordinator.html'), $component->html());
    }
});

test('a coordinator without a recognized college sees an explanatory empty state', function () {
    $coordinator = User::factory()->create(['college' => null]);
    $coordinator->assignRole('research_coordinator');
    $this->actingAs($coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);

    Livewire::test(ResearchTargetBoard::class)->assertViewHas('rows', [])
        ->assertSee('college')->assertSet('targetForm', []);
});

test('coordinators cannot invoke target editor actions', function (string $action) {
    $coordinator = User::factory()->create(['college' => User::COLLEGES['CAS']]);
    $coordinator->assignRole('research_coordinator');
    $this->actingAs($coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);

    Livewire::test(ResearchTargetBoard::class)->call($action)->assertForbidden();
    expect(ResearchAnnualTarget::count())->toBe(0);
})->with(['editTargets', 'saveTargets', 'cancelEditing']);

test('a head using a coordinator workspace cannot edit targets', function () {
    $head = ($this->editor)();
    $head->assignRole('research_coordinator');
    $head->update(['college' => User::COLLEGES['CAS']]);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);

    Livewire::test(ResearchTargetBoard::class)->call('editTargets')->assertForbidden();
});

test('switching to a coordinator workspace clears already loaded institutional editor data', function () {
    $head = ($this->editor)();
    $head->assignRole('research_coordinator');
    $head->update(['college' => User::COLLEGES['CAS']]);
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'publications_target' => 876543,
        'indicators' => ['publications' => ['allocations' => ['CAS' => 12, 'CICS' => 765432]]],
    ]);
    $component = Livewire::test(ResearchTargetBoard::class)->call('editTargets')
        ->assertSet('targetForm.indicators.publications.total', 876543);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $component->refresh()->assertSet('targetForm', [])->assertSet('editingTargets', false);

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))->not->toContain('876543', '765432');
});

test('faculty workspaces cannot load research targets even when the account is a head', function (bool $headRole) {
    $user = User::factory()->create();
    $user->assignRole($headRole ? 'research_head' : 'faculty');
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);

    Livewire::test(ResearchTargetBoard::class)->assertForbidden();
})->with([false, true]);

test('research target validation rejects invalid values and unknown keys', function (string $field, mixed $value, string $error) {
    ($this->editor)();
    $form = $this->targetForm;
    data_set($form, $field, $value);

    Livewire::test(ResearchTargetBoard::class)->call('editTargets', true)->set('targetForm', $form)
        ->call('saveTargets')->assertHasErrors($error);

    expect(ResearchAnnualTarget::count())->toBe(0);
})->with([
    'negative count' => ['indicators.publications.total', -1, 'targetForm.indicators.publications.total'],
    'fractional count' => ['indicators.completed_research.total', 2.5, 'targetForm.indicators.completed_research.total'],
    'fractional allocation' => ['indicators.publications.allocations.CAS', 1.5, 'targetForm.indicators.publications.allocations.CAS'],
    'percentage above 100' => ['indicators.faculty_engaged.total', 100.1, 'targetForm.indicators.faculty_engaged.total'],
    'percentage precision' => ['indicators.faculty_engaged.total', 50.123, 'targetForm.indicators.faculty_engaged.total'],
    'negative percentage' => ['indicators.faculty_engaged.allocations.CAS', -0.1, 'targetForm.indicators.faculty_engaged.allocations.CAS'],
    'unknown indicator' => ['indicators.unknown', ['total' => 1, 'allocations' => []], 'targetForm.indicators'],
    'unknown college' => ['indicators.publications.allocations.UNKNOWN', 1, 'targetForm.indicators.publications.allocations'],
    'dates reversed' => ['ends_on', '2026-07-01', 'targetForm.ends_on'],
    'period exceeds two years' => ['ends_on', '2029-01-01', 'targetForm.ends_on'],
]);

test('count allocations require a total and may not exceed it', function (mixed $total, array $allocations) {
    ($this->editor)();
    $form = $this->targetForm;
    $form['indicators']['publications']['total'] = $total;
    $form['indicators']['publications']['allocations'] = $allocations;

    Livewire::test(ResearchTargetBoard::class)->call('editTargets', true)->set('targetForm', $form)
        ->call('saveTargets')->assertHasErrors('targetForm.indicators.publications.total');

    expect(ResearchAnnualTarget::count())->toBe(0);
})->with([
    'missing total' => ['', ['CAS' => 2, 'CICS' => 4]],
    'zero allocation without total' => ['', ['CAS' => 0]],
    'overallocated total' => [5, ['CAS' => 2, 'CICS' => 4]],
]);

test('adding a year does not overwrite an existing year', function () {
    ($this->editor)();
    $target = ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'publications_target' => 55]);

    Livewire::test(ResearchTargetBoard::class)->call('editTargets', true)
        ->set('targetForm', $this->targetForm)->call('saveTargets')
        ->assertHasErrors('targetForm.academic_year');

    expect($target->refresh()->publications_target)->toBe(55)->and(ResearchAnnualTarget::count())->toBe(1);
});

test('research workspaces provide the shared target board at their designated entry point', function (string $role, string $workspace, string $routeName) {
    $user = User::factory()->create(['college' => User::COLLEGES['CAS']]);
    $user->assignRole($role);

    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route($routeName))->assertSuccessful()->assertSee('Research targets')->assertSeeLivewire(ResearchTargetBoard::class);
})->with([
    'head' => ['research_head', User::WORKSPACE_RESEARCH_HEAD, 'research_head.targets.index'],
    'coordinator' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, 'research_coordinator.dashboard'],
    'secretary' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, 'research_secretary.dashboard'],
]);
