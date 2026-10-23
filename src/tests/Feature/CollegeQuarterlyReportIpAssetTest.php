<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->input = ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1'];
    $this->asset = [
        'id' => (string) Str::uuid(), 'title' => 'Solar & coastal sensor', 'type' => 'Utility Model',
        'inventors' => "Ana Researcher\nBen Researcher", 'date' => '2026-08-14',
        'remarks' => 'Filed with IPOPHL', 'evidence_url' => 'https://example.org/evidence/sensor',
    ];
});

test('coordinators can save IP assets and include them in preview and Word export', function () {
    $this->post(route('research_coordinator.reports.store'), [...$this->input, 'ip_assets' => [$this->asset]])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->ip_assets)->toEqual([$this->asset]);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSeeText('Intellectual Property Assets')->assertSee('data-quarterly-ip-asset', false);
    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSeeText('Solar & coastal sensor')->assertSeeText('Utility Model')->assertSeeText('Filed with IPOPHL')
        ->assertSee('https://example.org/evidence/sensor');

    $response = $this->get(route('research_coordinator.reports.download', ['year' => 2026, 'quarter' => 3]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'quarterly-ip-test-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $xml = $archive->getFromName('word/document.xml');
        expect($xml)->toContain('Intellectual Property Assets', 'Solar &amp; coastal sensor', 'Utility Model', 'Filed with IPOPHL', 'https://example.org/evidence/sensor');
    } finally {
        $archive->close();
        unlink($path);
    }
});

test('submitting freezes IP assets in the numbered accomplishment report history', function () {
    $this->post(route('research_coordinator.reports.store'), [...$this->input, 'intent' => 'submit', 'ip_assets' => [$this->asset]])
        ->assertRedirect()->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $submission = $report->submissions()->sole();
    expect($submission->version_number)->toBe(1)->and($submission->snapshot['ip_assets'])->toEqual([$this->asset]);
    $report->update(['ip_assets' => [[...$this->asset, 'title' => 'Changed after submission']]]);
    expect($submission->fresh()->asReport()->ip_assets)->toEqual([$this->asset]);
    $this->get(route('college-quarterly-submissions.print', $submission))
        ->assertOk()->assertSeeText('Solar & coastal sensor')->assertDontSeeText('Changed after submission');
});

test('IP asset types accept the four workbook classifications', function (string $type) {
    $this->post(route('research_coordinator.reports.store'), [...$this->input,
        'ip_assets' => [[...$this->asset, 'type' => $type, 'evidence_url' => null]],
    ])->assertSessionHasNoErrors();
    expect(CollegeQuarterlyReport::sole()->ip_assets[0]['type'])->toBe($type);
})->with(['Invention/Patent', 'Utility Model', 'Industrial Design', 'Copyright']);

test('IP assets validate complete bounded rows before saving', function (array $changes, string $field) {
    $this->postJson(route('research_coordinator.reports.store'), [...$this->input, 'ip_assets' => [[...$this->asset, ...$changes]]])
        ->assertUnprocessable()->assertJsonValidationErrors('ip_assets.0'.($field === '' ? '' : '.'.$field));
    expect(CollegeQuarterlyReport::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'invalid type' => [['type' => 'Trademark'], 'type'],
    'missing inventors' => [['inventors' => ''], 'inventors'],
    'missing date' => [['date' => ''], 'date'],
    'outside quarter' => [['date' => '2026-10-01'], 'date'],
    'invalid date' => [['date' => '2026-02-30'], 'date'],
    'long remarks' => [['remarks' => str_repeat('x', 2001)], 'remarks'],
    'unsafe link' => [['evidence_url' => 'javascript:alert(1)'], 'evidence_url'],
    'unexpected key' => [['project_id' => 1], ''],
]);

test('blank IP rows are discarded and older clients receive a saved UUID', function () {
    $asset = $this->asset;
    unset($asset['id']);
    $this->post(route('research_coordinator.reports.store'), [...$this->input, 'ip_assets' => [$asset, ['title' => ' ', 'type' => '', 'inventors' => '', 'date' => '']]])
        ->assertSessionHasNoErrors();
    $rows = CollegeQuarterlyReport::sole()->ip_assets;
    expect($rows)->toHaveCount(1)->and(Str::isUuid($rows[0]['id']))->toBeTrue();
});

test('IP assets reject duplicate identities and more than one hundred entries', function () {
    $this->postJson(route('research_coordinator.reports.store'), [...$this->input, 'ip_assets' => [$this->asset, $this->asset]])
        ->assertUnprocessable()->assertJsonValidationErrors('ip_assets.0.id');
    $rows = array_map(fn (): array => [...$this->asset, 'id' => (string) Str::uuid()], range(1, 101));
    $this->postJson(route('research_coordinator.reports.store'), [...$this->input, 'ip_assets' => $rows])
        ->assertUnprocessable()->assertJsonValidationErrors('ip_assets');
    expect(CollegeQuarterlyReport::count())->toBe(0);
});
