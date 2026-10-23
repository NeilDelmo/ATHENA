<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\User;
use App\Services\CollegeReportLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 23));
    Role::findOrCreate('research_head', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->report = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CICS'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($this->report, 'report')->create();
    $this->source = 'report:'.$this->report->id;
    $this->payload = ['revision' => 0, 'items' => [[
        'id' => $this->source, 'source_id' => $this->source, 'parent_id' => 'year:2026', 'name' => 'Quarterly report',
    ]]];
});

test('research head can move a report in the library without changing its recorded college period or submission', function () {
    $original = $this->report->fresh()->getAttributes();
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)
        ->assertSuccessful()->assertJsonPath('workspace.revision', 1);
    $this->head->refresh();
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))
        ->assertSuccessful()->assertSeeText('Quarterly report')->assertSeeText('Return for correction');
    $this->get(route('research_head.quarterly-reports.index', [
        'year' => 2026, 'college' => User::COLLEGES['CICS'], 'quarter' => 3,
    ]))->assertSuccessful()->assertDontSeeText('Submission history (1)');
    expect($this->report->fresh()->getAttributes())->toBe($original)
        ->and($this->report->submissions()->count())->toBe(1);
});

test('copied report instances survive reload without duplicating official submissions', function () {
    $copyId = (string) Str::uuid();
    $this->payload['items'][0]['id'] = $copyId;
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $this->head->refresh();
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))
        ->assertSuccessful()->assertSee('data-library-item="'.$copyId.'"', false);
    expect(CollegeQuarterlyReport::count())->toBe(1)
        ->and(CollegeQuarterlyReportSubmission::count())->toBe(1);
});

test('library rejects cycles missing destinations invalid sources and changed source identities', function (string $case) {
    $college = 'college:2026:'.sha1(User::COLLEGES['CICS']);
    if ($case === 'cycle') {
        $this->payload['items'] = [[
            'id' => 'year:2026', 'source_id' => 'year:2026', 'parent_id' => $college, 'name' => '2026',
        ]];
    } elseif ($case === 'destination') {
        $this->payload['items'][0]['parent_id'] = 'missing';
    } elseif ($case === 'source') {
        $this->payload['items'][0]['source_id'] = 'report:99999999';
    } elseif ($case === 'identity') {
        $this->payload['items'][0]['source_id'] = 'year:2026';
    }
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertUnprocessable();
    expect($this->head->fresh()->report_library_workspace)->toBeNull();
})->with(['cycle', 'destination', 'source', 'identity']);

test('a stale library save cannot overwrite a completed move', function () {
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $this->payload['items'][0]['name'] = 'Outdated';
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertConflict();
    expect($this->head->fresh()->report_library_workspace['items'][0]['name'])->toBe('Quarterly report');
});

test('only the research head can save library organization', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertForbidden();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertForbidden();
});

test('college library renders selectable folders and file clipboard controls', function () {
    $this->head->update(['name' => 'Dr. Elena Santos']);
    $this->report->latestSubmission->update(['submitted_by' => $this->head->id, 'submitted_at' => '2026-10-20 09:30:00']);
    $response = $this->get(route('research_head.quarterly-reports.index', ['year' => 2026]))
        ->assertSuccessful()->assertSeeText('Cut')->assertSeeText('Copy')->assertSeeText('Paste');
    if (getenv('ATHENA_EXPORT_COLLEGE_LIBRARY') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/college-library.html'), $response->getContent());
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-library-item]')->length)->toBe(6);
});

test('Details includes submission metadata and counts from the current folder contents', function () {
    $this->report->latestSubmission->update(['submitted_by' => $this->head->id, 'submitted_at' => '2026-10-20 09:30:00']);
    $location = ['year' => 2026, 'college' => User::COLLEGES['CICS']];
    $response = $this->get(route('research_head.quarterly-reports.index', $location))->assertSuccessful();
    $quarter = $response->viewData('libraryItems')->firstWhere('name', 'Q3');
    expect($quarter)->toMatchArray(['report_count' => 1, 'status' => 'Submitted', 'submitted_by' => $this->head->name])
        ->and($quarter['submitted_at'])->toBe('2026-10-20T01:30:00.000000Z')
        ->and($quarter['submitted_label'])->toBe('Oct 20, 2026 · 9:30 AM');
    $response->assertSeeText('Last submitted')->assertSeeText('Submitted by');
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $this->head->refresh();
    $moved = $this->get(route('research_head.quarterly-reports.index', $location))->assertSuccessful()
        ->viewData('libraryItems')->firstWhere('name', 'Q3');
    expect($moved)->toMatchArray(['report_count' => 0, 'submitted_at' => null, 'submitted_by' => null, 'description' => 'July–September · No reports']);
});

test('Details folder summaries use the newest submission and flag returned reports', function () {
    $this->report->latestSubmission->update(['submitted_at' => '2026-10-20 09:30:00']);
    $coordinator = User::factory()->create(['name' => 'Prof. Maria Cruz']);
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CTE'], 'reporting_year' => 2026, 'reporting_quarter' => 3,
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create([
        'submitted_at' => '2026-10-22 10:00:00', 'submitted_by' => $coordinator->id, 'returned_at' => now(),
    ]);
    $year = $this->get(route('research_head.quarterly-reports.index'))->assertSuccessful()
        ->viewData('libraryItems')->firstWhere('name', '2026');
    expect($year)->toMatchArray([
        'report_count' => 2, 'status' => 'Correction needed', 'submitted_by' => 'Prof. Maria Cruz',
        'submitted_label' => 'Oct 22, 2026 · 10:00 AM',
    ]);
});

test('new official submissions do not block unrelated library moves with existing name collisions', function () {
    $this->payload['items'][0]['parent_id'] = 'quarter:2026:'.sha1(User::COLLEGES['CTE']).':3';
    $this->payload['items'][0]['name'] = 'Q3 2026 accomplishment report';
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $otherReport = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CTE'], 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($otherReport, 'report')->create();
    $this->head->refresh();
    $this->payload['revision'] = 1;
    $id = 'college:2026:'.sha1(User::COLLEGES['CAS']);
    $this->payload['items'][] = ['id' => $id, 'source_id' => $id, 'parent_id' => 'college:2026:'.sha1(User::COLLEGES['CICS']), 'name' => 'CAS'];
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
});

test('copied report instances are paginated individually', function () {
    $this->payload['items'] = collect(range(1, 13))->map(fn (int $copy): array => [
        'id' => (string) Str::uuid(), 'source_id' => $this->source, 'parent_id' => 'year:2026', 'name' => 'Copy '.$copy,
    ])->all();
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $this->head->refresh();
    foreach ([1 => 12, 2 => 1] as $page => $expectedCount) {
        $response = $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'page' => $page]))
            ->assertSuccessful()->assertViewHas('reports', fn ($reports): bool => $reports->total() === 13);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        expect((new DOMXPath($document))->query('//article[@data-library-item]')->length)->toBe($expectedCount);
    }
});

test('name sorting uses natural order before pagination and remembers the selected direction', function () {
    $this->payload['items'] = collect(range(1, 13))->map(fn (int $copy): array => [
        'id' => (string) Str::uuid(), 'source_id' => $this->source, 'parent_id' => 'year:2026', 'name' => 'Copy '.$copy,
    ])->all();
    $this->putJson('/research-head/quarterly-accomplishment-reports/workspace', $this->payload)->assertSuccessful();
    $this->head->refresh();
    $url = route('research_head.quarterly-reports.index', ['year' => 2026]);
    $ascending = $this->get($url.'&sort=name&direction=asc')->assertSuccessful()->viewData('libraryPageFiles');
    expect($ascending->pluck('name')->all())->toBe(array_map(fn (int $copy): string => 'Copy '.$copy, range(1, 12)));
    $descending = $this->get($url.'&sort=name&direction=desc')->assertSuccessful()->viewData('libraryPageFiles');
    expect($descending->pluck('name')->all())->toBe(array_map(fn (int $copy): string => 'Copy '.$copy, range(13, 2)));
    $remembered = $this->get($url.'&page=2')->assertSuccessful()->assertViewHas('sortDirection', 'desc')->viewData('libraryPageFiles');
    expect($remembered->pluck('name')->all())->toBe(['Copy 1']);
});

test('report details sort by latest submission with empty folders last and reject unknown fields', function () {
    $this->report->latestSubmission->update(['submitted_at' => '2026-10-20 09:30:00']);
    $newer = CollegeQuarterlyReport::factory()->create(['college' => User::COLLEGES['CTE'], 'reporting_year' => 2026, 'reporting_quarter' => 3]);
    CollegeQuarterlyReportSubmission::factory()->for($newer, 'report')->create(['submitted_at' => '2026-10-22 09:30:00']);
    $items = $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'sort' => 'submitted_at', 'direction' => 'desc']))
        ->assertSuccessful()->viewData('libraryItems');
    expect($items->take(2)->pluck('name')->all())->toBe(['CTE', 'CICS'])
        ->and($items->last()['submitted_at'])->toBeNull();
    $this->getJson(route('research_head.quarterly-reports.index', ['sort' => 'unknown', 'direction' => 'backwards']))
        ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'direction']);
});

test('Details sorts counts numerically and keeps folders before files in both directions', function () {
    $items = collect([
        ['id' => 'file', 'kind' => 'file', 'name' => 'Report', 'report_count' => 1],
        ['id' => 'ten', 'kind' => 'folder', 'name' => 'Ten reports', 'report_count' => 10],
        ['id' => 'two', 'kind' => 'folder', 'name' => 'Two reports', 'report_count' => 2],
    ]);
    $library = app(CollegeReportLibrary::class);
    expect($library->sort($items, 'report_count', 'asc')->pluck('id')->all())->toBe(['two', 'ten', 'file'])
        ->and($library->sort($items, 'report_count', 'desc')->pluck('id')->all())->toBe(['ten', 'two', 'file']);
});
