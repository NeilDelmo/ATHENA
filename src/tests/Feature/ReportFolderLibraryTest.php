<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 18));
    Role::findOrCreate('research_head', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->report = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    $this->submission = CollegeQuarterlyReportSubmission::factory()->for($this->report, 'report')->create(['version_number' => 1]);
    $this->location = ['year' => 2026, 'college' => User::COLLEGES['CICS'], 'quarter' => 3];
});

test('college library opens year college and quarter folders before report actions', function () {
    CollegeQuarterlyReport::factory()->create(['college' => 'Private draft college', 'reporting_year' => 2026]);
    $this->get(route('research_head.quarterly-reports.index'))->assertOk()
        ->assertSee(route('research_head.quarterly-reports.index', ['year' => 2026]))
        ->assertDontSeeText('Return for correction')->assertDontSeeText('Private draft college');
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))->assertOk()
        ->assertSee(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => User::COLLEGES['CICS']]))
        ->assertDontSeeText('Return for correction');
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => User::COLLEGES['CICS']]))->assertOk()
        ->assertSee(route('research_head.quarterly-reports.index', $this->location))
        ->assertSeeText('July–September')->assertSeeText('Not submitted');
    $response = $this->get(route('research_head.quarterly-reports.index', $this->location))->assertOk()
        ->assertSeeText('Submission history')->assertSeeText('Return for correction')
        ->assertSee(route('college-quarterly-submissions.download', $this->submission))
        ->assertDontSeeText('Download Word');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $download = $xpath->query('//a[@aria-label="Download report"]')->item(0);
    expect($download)->not->toBeNull()
        ->and($xpath->query('.//svg', $download)->length)->toBe(1)
        ->and(trim($download->textContent))->toBe('');
});

test('college folder filters cannot mix other colleges quarters or years', function () {
    foreach ([[2026, 2, User::COLLEGES['CICS']], [2025, 3, User::COLLEGES['CICS']], [2026, 3, User::COLLEGES['CTE']]] as [$year, $quarter, $college]) {
        $report = CollegeQuarterlyReport::factory()->create(['reporting_year' => $year, 'reporting_quarter' => $quarter, 'college' => $college]);
        CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create(['version_number' => 1]);
    }
    $this->get(route('research_head.quarterly-reports.index', $this->location))->assertOk()
        ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1 && $reports->first()->is($this->report));
    $this->getJson(route('research_head.quarterly-reports.index', ['quarter' => 5]))
        ->assertUnprocessable()->assertJsonValidationErrors('quarter');
});

test('RMS library browses years and quarters without creating empty drafts', function () {
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2025, 'reporting_quarter' => 4]);
    $this->get(route('research_head.rms-quarterly-reports.index'))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.library')
        ->assertSee(route('research_head.rms-quarterly-reports.index', ['year' => 2025]))
        ->assertSee(route('research_head.rms-quarterly-reports.index', ['year' => 2026]));
    $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026]))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.library')
        ->assertSee(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertSeeText('1 college report');
    $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.index')->assertSee('data-rms-section-folders', false);
    expect(RmsQuarterlyReport::count())->toBe(1);
});

test('returning a report keeps the reader in its folder and preserves access to history', function () {
    $this->patch(route('research_head.quarterly-reports.return', $this->report), ['submission_id' => $this->submission->id, 'reason' => 'Add the missing evidence.'])
        ->assertSessionHasNoErrors()->assertRedirect(route('research_head.quarterly-reports.index', $this->location));
    $this->get(route('research_head.quarterly-reports.index', $this->location))->assertOk()
        ->assertSeeText('Returned for correction')->assertSeeText('Add the missing evidence.')
        ->assertSee(route('college-quarterly-submissions.print', $this->submission));
});

test('report library locations expose a view context menu and wheel sizing without toolbar controls', function (string $routeName, array $location) {
    $response = $this->get(route($routeName, $location))->assertSuccessful()
        ->assertSee('@wheel="resizeWithWheel($event)"', false)
        ->assertSee('@contextmenu="openViewMenu($event)"', false)
        ->assertDontSee('aria-label="Item size"', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $surfaces = $xpath->query('//*[@data-library-view-surface]');
    expect($surfaces->length)->toBeGreaterThan(0);
    foreach ($surfaces as $surface) {
        expect($surface->getAttribute('tabindex'))->toBe('0');
    }

    $menu = $xpath->query('//*[@role="menu" and @aria-label="View"]')->item(0);
    expect($menu)->not->toBeNull();
    expect($menu->hasAttribute('x-cloak'))->toBeTrue()
        ->and($menu->hasAttribute('x-show'))->toBeTrue()
        ->and($xpath->query('.//button[@role="menuitemradio"]', $menu)->length)->toBe(4)
        ->and($xpath->query('//button[not(@role="menuitemradio") and (normalize-space(.)="Icons" or normalize-space(.)="Details")]')->length)->toBe(0);
    foreach (['small' => 'Small icons', 'medium' => 'Medium icons', 'large' => 'Large icons', 'list' => 'Details'] as $option => $label) {
        $button = $xpath->query('.//button[@type="button" and @role="menuitemradio" and @data-view-option="'.$option.'"]', $menu)->item(0);
        expect($button)->not->toBeNull();
        expect(trim($button->textContent))->toBe($label)
            ->and($button->hasAttribute('aria-checked') || $button->hasAttribute(':aria-checked') || $button->hasAttribute('x-bind:aria-checked'))->toBeTrue();
    }
})->with([
    'college years' => ['research_head.quarterly-reports.index', []],
    'college folders' => ['research_head.quarterly-reports.index', ['year' => 2026]],
    'college quarters' => ['research_head.quarterly-reports.index', ['year' => 2026, 'college' => User::COLLEGES['CICS']]],
    'college report files' => ['research_head.quarterly-reports.index', ['year' => 2026, 'college' => User::COLLEGES['CICS'], 'quarter' => 3]],
    'RMS years' => ['research_head.rms-quarterly-reports.index', []],
    'RMS quarters' => ['research_head.rms-quarterly-reports.index', ['year' => 2026]],
    'RMS section folders' => ['research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]],
]);
