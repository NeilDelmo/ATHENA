<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use Illuminate\Support\Facades\File;
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
        ->assertSeeText('July–September')->assertSeeText('No reports');
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

test('RMS overview groups all four quarters by year without creating empty drafts', function () {
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2025, 'reporting_quarter' => 4]);
    foreach (array_diff(array_values(User::COLLEGES), [$this->report->college]) as $college) {
        $source = CollegeQuarterlyReport::factory()->create([
            'college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
        ]);
        CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['snapshot' => ['college' => $college]]);
    }
    $response = $this->get(route('research_head.rms-quarterly-reports.index'))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.library')
        ->assertSeeText('RMS quarterly reports')
        ->assertViewHas('selectedYear', fn ($year): bool => $year === null)
        ->assertViewHas('reportYears', fn ($years): bool => $years->all() === [2026, 2025])
        ->assertViewHas('statuses', fn ($statuses): bool => $statuses->get('2026-3')['ready'] && $statuses->get('2026-3')['submitted_count'] === 6)
        ->assertDontSee('data-library-view-surface', false)
        ->assertDontSee('@contextmenu="openViewMenu($event)"', false);
    if (getenv('ATHENA_EXPORT_RMS_UI') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/rms-overview.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ([2026, 2025] as $year) {
        foreach (range(1, 4) as $quarter) {
            $url = route('research_head.rms-quarterly-reports.index', compact('year', 'quarter'));
            $link = $xpath->query('//section[@aria-labelledby="rms-year-'.$year.'"]//a[@href="'.$url.'"]')->item(0);
            expect($link)->not->toBeNull();
            expect(trim($link->textContent))->toContain('Open report', 'for '.$year.' Q'.$quarter);
            $downloadUrl = route('research_head.rms-quarterly-reports.download', compact('year', 'quarter'));
            $downloads = $xpath->query('//a[@href="'.$downloadUrl.'"]');
            expect($downloads->length)->toBe($year === 2026 && $quarter === 3 ? 1 : 0);
            if ($downloads->length === 1) {
                expect(trim($downloads->item(0)->textContent))->toContain('Download Excel', 'for '.$year.' Q'.$quarter);
            }
        }
    }
    expect($xpath->query('//*[@data-rms-report-period]//button[@disabled]')->length)->toBe(7);
    $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026]))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.library')
        ->assertSee(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertViewHas('statuses', fn ($statuses): bool => $statuses->get('2026-3')['ready']);
    $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertViewIs('research_head.rms-quarterly-reports.index')->assertSee('data-rms-section-navigation', false);
    expect(RmsQuarterlyReport::count())->toBe(1);
});

test('RMS year filtering keeps quarter counts and drafts in the selected year', function () {
    $source = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2025, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create();
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2025, 'reporting_quarter' => 3]);
    RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 4]);

    $response = $this->get(route('research_head.rms-quarterly-reports.index', ['year' => 2025]))->assertOk()
        ->assertViewHas('selectedYear', 2025)
        ->assertViewHas('reportYears', fn ($years): bool => $years->all() === [2025])
        ->assertViewHas('statuses', fn ($statuses): bool => $statuses->keys()->all() === ['2025-1', '2025-2', '2025-3', '2025-4'] && $statuses->get('2025-3')['submitted_count'] === 1)
        ->assertViewHas('drafts', fn ($drafts): bool => $drafts->keys()->all() === ['2025-3']);
    foreach (range(1, 4) as $quarter) {
        $response->assertSee(route('research_head.rms-quarterly-reports.index', ['year' => 2025, 'quarter' => $quarter]))
            ->assertDontSee(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => $quarter]))
            ->assertDontSee(route('research_head.rms-quarterly-reports.download', ['year' => 2026, 'quarter' => $quarter]));
    }
    expect(RmsQuarterlyReport::count())->toBe(2);
});

test('RMS overview excludes college reports whose latest submission was returned', function () {
    foreach ([[2026, User::COLLEGES['CTE']], [2024, User::COLLEGES['CICS']]] as [$year, $college]) {
        $source = CollegeQuarterlyReport::factory()->create([
            'college' => $college, 'reporting_year' => $year, 'reporting_quarter' => 3, 'submitted_at' => now(),
        ]);
        CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['version_number' => 1]);
        CollegeQuarterlyReportSubmission::factory()->for($source, 'report')->create(['version_number' => 2, 'returned_at' => now()]);
    }
    $this->get(route('research_head.rms-quarterly-reports.index'))->assertOk()
        ->assertViewHas('reportYears', fn ($years): bool => $years->all() === [2026])
        ->assertViewHas('statuses', fn ($statuses): bool => $statuses->get('2026-3')['submitted_count'] === 1 && ! $statuses->get('2026-3')['ready']);
    expect(RmsQuarterlyReport::count())->toBe(0);
});

test('returning a report keeps the reader in its folder and preserves access to history', function () {
    $this->patch(route('research_head.quarterly-reports.return', $this->report), ['submission_id' => $this->submission->id, 'reason' => 'Add the missing evidence.'])
        ->assertSessionHasNoErrors()->assertRedirect(route('research_head.quarterly-reports.index', $this->location));
    $this->get(route('research_head.quarterly-reports.index', $this->location))->assertOk()
        ->assertSeeText('Returned for correction')->assertSeeText('Add the missing evidence.')
        ->assertSee(route('college-quarterly-submissions.print', $this->submission));
});

test('college library locations expose a view context menu and wheel sizing without toolbar controls', function (string $routeName, array $location) {
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

    $menu = $xpath->query('//*[@role="menu" and @*[name()=":aria-label"]="menuLabel"]')->item(0);
    expect($menu)->not->toBeNull();
    expect($menu->hasAttribute('x-cloak'))->toBeTrue()
        ->and($menu->hasAttribute('x-show'))->toBeTrue()
        ->and($xpath->query('.//button[@role="menuitemradio" and @data-view-option]', $menu)->length)->toBe(4)
        ->and($xpath->query('.//button[@role="menuitemradio" and not(@data-view-option)]', $menu)->length)->toBe(5)
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
]);
