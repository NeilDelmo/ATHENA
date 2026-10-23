<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

function facultyAccomplishmentDocumentParts(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'faculty-accomplishment-');
    file_put_contents($path, $contents);
    $zip = new ZipArchive;
    try {
        expect($zip->open($path))->toBeTrue();
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $parts[$name] = $zip->getFromName($name);
        }

        return $parts;
    } finally {
        $zip->close();
        unlink($path);
    }
}

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    Storage::fake('local');
    Role::findOrCreate('research_coordinator', 'web');
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->faculty = User::factory()->create(['college' => $this->coordinator->college]);
    $this->project = TopicProposal::create([
        'user_id' => $this->faculty->id, 'title' => 'Faculty evidence research', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31'],
    ]);
    $this->proof = function (string $name = 'faculty-proof.png'): array {
        $file = UploadedFile::fake()->image($name);
        $path = $file->store('progress-reports/'.$this->project->id.'/evidence', 'local');

        return ['id' => (string) Str::uuid(), 'path' => $path, 'name' => $name, 'mime_type' => 'image/png',
            'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath())];
    };
    $this->monitoring = fn (array $files, array $attributes = []) => ProjectProgressReport::create([
        'topic_id' => $this->project->id, 'submitted_by' => $this->faculty->id,
        'reporting_date' => '2026-09-30', 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'version_number' => 1, 'progress_percentage' => 20, 'submitted_at' => now(), 'accomplishments' => 'Collected participant responses',
        'work_plan' => [['activity' => 'Collect participant responses', 'evidence' => $files]], ...$attributes,
    ]);
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->period = ['year' => 2026, 'quarter' => 3];
    $this->input = ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1'];
});

test('submitted faculty image proof appears in accomplishment form print and Word without manual activity imports', function () {
    ($this->monitoring)([($this->proof)()]);
    $response = $this->get(route('research_coordinator.reports.index', $this->period))->assertOk()
        ->assertViewHas('reportEvidence', fn (array $evidence): bool => count($evidence) === 1 && $evidence[0]['original_filename'] === 'faculty-proof.png')
        ->assertViewHas('reportData', fn (array $data): bool => $data['presentations'] === [] && $data['publications'] === [])
        ->assertSeeText('Faculty evidence research')->assertSeeText('Collect participant responses');
    $print = $this->get(route('research_coordinator.reports.print', $this->period))->assertOk()->assertSeeText('Collect participant responses');
    $parts = facultyAccomplishmentDocumentParts($this->get(route('research_coordinator.reports.download', $this->period))->assertOk()->getContent());
    expect($parts)->toHaveKey('word/media/quarterly-evidence-1.png')
        ->and($parts['word/document.xml'])->toContain('Collect participant responses', 'Faculty evidence research');
    expect(CollegeQuarterlyReport::count())->toBe(0);
    if (getenv('ATHENA_EXPORT_FACULTY_ACCOMPLISHMENT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/faculty-accomplishment-form.html'), $response->getContent());
        File::put(storage_path('framework/testing/faculty-accomplishment-print.html'), $print->getContent());
    }
});

test('college report evidence imports wait for office secretary forwarding', function () {
    $proof = ($this->proof)('awaiting-office.png');
    $report = ($this->monitoring)([$proof], ['review_stage' => 'secretary']);
    $service = app(CollegeQuarterlyReportFacultyEvidence::class);

    expect($service->forQuarter($this->coordinator, 2026, 3))->toBe([]);
    $report->update(['review_stage' => 'head', 'forwarded_at' => now()]);
    expect(array_column($service->forQuarter($this->coordinator, 2026, 3), 'path'))->toBe([$proof['path']]);
});

test('faculty evidence collection respects college notice and calendar quarter', function () {
    ($this->monitoring)([($this->proof)('inside.png')]);
    ($this->monitoring)([($this->proof)('previous.png')], ['reporting_date' => '2026-06-30', 'reporting_quarter' => 2]);
    ($this->monitoring)([($this->proof)('draft.png')], ['reporting_date' => '2026-08-31', 'version_number' => 2, 'submission_status' => 'prepared']);
    expect(array_column(app(CollegeQuarterlyReportFacultyEvidence::class)->forQuarter($this->coordinator, 2026, 3), 'original_filename'))->toBe(['inside.png']);
    $this->project->update(['notice_to_proceed_issued_at' => null]);
    expect(app(CollegeQuarterlyReportFacultyEvidence::class)->forQuarter($this->coordinator, 2026, 3))->toBe([]);
    $this->project->update(['notice_to_proceed_issued_at' => '2026-01-01']);
    $this->faculty->update(['college' => User::COLLEGES['CTE']]);
    expect(app(CollegeQuarterlyReportFacultyEvidence::class)->forQuarter($this->coordinator, 2026, 3))->toBe([]);
});

test('submitted accomplishment reports freeze faculty proof independently of later source changes', function () {
    $file = ($this->proof)();
    $source = ($this->monitoring)([$file]);
    $this->post(route('research_coordinator.reports.store'), [...$this->input, 'intent' => 'submit'])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $frozen = $report->snapshot['evidence'][0];
    expect($frozen['source'])->toBe('faculty_report')->and($frozen['path'])->not->toBe($file['path'])
        ->and($frozen['path'])->toStartWith('college-quarterly-reports/'.$report->id.'/faculty-evidence/');
    Storage::disk('local')->delete($file['path']);
    $source->update(['work_plan' => []]);
    $this->get(route('research_coordinator.reports.evidence', [$report, 0]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $parts = facultyAccomplishmentDocumentParts($this->get(route('research_coordinator.reports.download', $this->period))->assertOk()->getContent());
    expect($parts)->toHaveKey('word/media/quarterly-evidence-1.png');
    $this->get(route('research_coordinator.reports.print', $this->period))->assertOk()->assertSeeText('Collect participant responses');
});

test('faculty PDF proof is listed with a file link in accomplishment Word instead of breaking image export', function () {
    $path = 'progress-reports/'.$this->project->id.'/evidence/certificate.pdf';
    Storage::disk('local')->put($path, "%PDF-1.7\nFaculty certificate");
    ($this->monitoring)([['id' => (string) Str::uuid(), 'path' => $path, 'name' => 'certificate.pdf',
        'mime_type' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'checksum' => Storage::disk('local')->checksum($path, ['checksum_algo' => 'sha256'])]]);
    $this->get(route('research_coordinator.reports.print', $this->period))->assertOk()->assertSeeText('certificate.pdf');
    $parts = facultyAccomplishmentDocumentParts($this->get(route('research_coordinator.reports.download', $this->period))->assertOk()->getContent());
    expect($parts['word/document.xml'])->toContain('certificate.pdf', 'w:hyperlink')
        ->and($parts['word/_rels/document.xml.rels'])->toContain('/hyperlink', 'TargetMode="External"');

    $this->post(route('research_coordinator.reports.store'), [...$this->input, 'intent' => 'submit'])->assertSessionHasNoErrors();
    $submission = CollegeQuarterlyReport::sole()->submissions()->sole();
    $parts = facultyAccomplishmentDocumentParts($this->get(route('research_coordinator.reports.download', $this->period))->assertOk()->getContent());
    expect($parts['word/_rels/document.xml.rels'])->toContain(route('college-quarterly-submissions.evidence', [$submission, 0]));
});

test('failed college saving removes copied faculty evidence without deleting the source', function () {
    $file = ($this->proof)();
    ($this->monitoring)([$file]);
    $this->mock(CollegeQuarterlyReportData::class)->shouldReceive('build')->once()->andThrow(new RuntimeException('Snapshot failed'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('research_coordinator.reports.store'), [...$this->input, 'intent' => 'submit']))->toThrow(RuntimeException::class, 'Snapshot failed');
    expect(CollegeQuarterlyReport::count())->toBe(0)->and(Storage::disk('local')->allFiles('college-quarterly-reports'))->toBe([]);
    Storage::disk('local')->assertExists($file['path']);
});

test('finalized faculty file links allow the Research Head but keep college drafts and other colleges private', function () {
    Role::findOrCreate('research_head', 'web');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $path = 'college-quarterly-reports/frozen-certificate.pdf';
    Storage::disk('local')->put($path, "%PDF-1.7\nFaculty certificate");
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $this->coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'snapshot' => ['presentations' => [], 'publications' => [], 'research' => []], 'submitted_at' => now(),
        'evidence' => [['path' => $path, 'source' => 'faculty_report', 'mime_type' => 'application/pdf',
            'original_filename' => 'certificate.pdf', 'caption' => 'Faculty evidence certificate']],
    ]);
    $parts = facultyAccomplishmentDocumentParts($this->get(route('research_coordinator.reports.download', $this->period))->assertOk()->getContent());
    expect($parts['word/_rels/document.xml.rels'])->toContain('/college-quarterly-reports/'.$report->id.'/evidence/0');
    $url = '/college-quarterly-reports/'.$report->id.'/evidence/0';
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->get($url)
        ->assertOk()->assertDownload('certificate.pdf');
    $report->update(['submitted_at' => null]);
    $this->get($url)->assertForbidden();
    $report->update(['submitted_at' => now()]);
    $this->coordinator->update(['college' => User::COLLEGES['CTE']]);
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])->get($url)
        ->assertForbidden();
});
