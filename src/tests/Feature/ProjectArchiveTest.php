<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringToolDocumentService;
use App\Services\ProjectArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_secretary', 'research_office'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    Storage::fake('local');
    $this->withoutVite();
    $this->owner = User::factory()->create(['college' => 'Science']);
    $this->owner->assignRole(['faculty', 'faculty_researcher']);
    $this->topic = TopicProposal::create([
        'user_id' => $this->owner->id, 'title' => 'Water Research', 'status' => 'approved',
        'estimated_budget' => 25000, 'estimated_duration_months' => 12,
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_path' => 'notices/official.pdf',
    ]);
    $this->actingAs($this->owner)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $this->store = function (string $path, string $contents = '%PDF-1.7 official paper'): string {
        Storage::disk('local')->put($path, $contents);

        return $path;
    };
    ($this->store)('notices/official.pdf');
    $this->version = function (int $number = 1) {
        $path = ($this->store)('proposal-packages/'.$this->topic->id.'/v'.$number.'/proposal.pdf');
        $version = $this->topic->versions()->create([
            'submitted_by' => $this->owner->id, 'version_number' => $number,
            'submission_type' => $number === 1 ? 'initial' : 'revision',
            'title' => $this->topic->title, 'file_path' => $path, 'original_filename' => 'proposal.pdf',
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'position' => 0,
            'file_path' => $path, 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        ]);

        return $version;
    };
    $this->monitoring = function (array $attributes = []) {
        return $this->topic->progressReports()->create([
            'submitted_by' => $this->owner->id, 'reporting_date' => '2026-03-31',
            'reporting_year' => 2026, 'reporting_quarter' => 1, 'version_number' => 1,
            'progress_percentage' => 30, 'accomplishments' => 'Field work complete.', 'submitted_at' => now(),
            'official_pdf_path' => ($this->store)('progress-reports/'.$this->topic->id.'/'.Str::uuid().'.pdf'),
            'official_pdf_filename' => 'monitoring.pdf', 'official_pdf_checksum' => hash('sha256', '%PDF-1.7 official paper'), ...$attributes,
        ]);
    };
    $this->narrative = function (array $attributes = []) {
        return $this->topic->narrativeReports()->create([
            'submitted_by' => $this->owner->id, 'report_type' => 'progress', 'submission_date' => '2026-03-31',
            'researchers' => $this->owner->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
            'budget' => 25000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Field work complete.',
            'introduction' => 'Water research.', 'objectives' => 'Measure water quality.',
            'methodology' => 'Field sampling.', 'results_discussion' => 'Samples collected.', 'photos' => [],
            'submitted_at' => now(), 'official_pdf_path' => ($this->store)('narrative-progress-reports/'.$this->topic->id.'/'.Str::uuid().'.pdf'),
            'official_pdf_filename' => 'progress.pdf', 'official_pdf_checksum' => hash('sha256', '%PDF-1.7 official paper'), ...$attributes,
        ]);
    };
    $this->readArchive = function (?User $viewer = null): array {
        $response = app(ProjectArchive::class)->download($this->topic->fresh(), $viewer ?? $this->owner);
        expect($response)->toBeInstanceOf(BinaryFileResponse::class);
        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        try {
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
            }

            return $entries;
        } finally {
            $zip->close();
            unlink($path);
        }
    };
});

test('project archive includes every submitted edition and released record with a readable index', function () {
    ($this->version)(1);
    $version = ($this->version)(2);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'position' => 1,
        'file_path' => ($this->store)('signed/proposal.pdf'), 'original_filename' => '../unsafe\\signed.pdf',
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
    ]);
    $this->topic->update([
        'signed_approval_path' => ($this->store)('signed/approval.pdf'),
        'notice_to_proceed_path' => ($this->store)('notices/released.pdf'),
    ]);
    ($this->monitoring)();
    ($this->monitoring)(['version_number' => 2]);
    ($this->narrative)();
    ($this->narrative)(['report_type' => 'terminal']);
    $this->topic->projectDocuments()->create([
        'uploaded_by' => $this->owner->id, 'category' => 'supporting_files', 'title' => 'Reference',
        'file_path' => ($this->store)('project-documents/'.$this->topic->id.'/reference.pdf'),
        'original_filename' => 'reference.pdf', 'mime_type' => 'application/pdf', 'file_size' => 24,
    ]);

    $entries = ($this->readArchive)();
    $names = implode("\n", array_keys($entries));
    expect($entries)->toHaveKey('INDEX.txt')->toHaveKey('manifest.json');
    expect($names)->toContain('01-proposal-papers/version-1-', '01-proposal-papers/version-2-', '02-signed-documents/', '03-notice-to-proceed/', '04-monitoring-reports/', '05-progress-reports/', '06-terminal-reports/', '07-supporting-files/');
    expect($names)->not->toContain('..', '\\');
    $manifest = json_decode($entries['manifest.json'], true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['project']['id'])->toBe($this->topic->id)
        ->and($manifest['missing_files'])->toBe(0)
        ->and(collect($manifest['files'])->where('category', 'Monitoring Tool')->pluck('edition')->all())->toBe(['Version 1', 'Version 2']);
    expect($entries['INDEX.txt'])->toContain('Water Research', 'Version 1', 'Version 2', 'Submitted', 'No missing');
    $page = $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('data-project-archive', false)->assertSee(route('topics.archive', $this->topic));
    if (getenv('ATHENA_TEST_EXPORT_UI') === '1') {
        file_put_contents(storage_path('framework/testing/project-archive-page.html'), $page->getContent());
    }
});

test('project archive records missing and unsafe stored paths instead of claiming completeness', function () {
    $version = ($this->version)();
    foreach (['missing/paper.pdf', '../outside.pdf', 'C:\\private\\secret.pdf'] as $index => $path) {
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => $index + 1,
            'file_path' => $path, 'original_filename' => 'paper.pdf',
        ]);
    }
    $entries = ($this->readArchive)();
    $manifest = json_decode($entries['manifest.json'], true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['missing_files'])->toBe(3)
        ->and(collect($manifest['files'])->where('status', 'included'))->toHaveCount(2)
        ->and($entries['INDEX.txt'])->toContain('INCOMPLETE', 'missing', 'unsafe');
    expect($entries['manifest.json'])->not->toContain('C:\\\\private', '../outside');
});

test('project archive excludes prepared reports and follows report forwarding access', function (string $workspace, array $included) {
    ($this->monitoring)(['review_stage' => 'secretary', 'official_pdf_filename' => 'unforwarded-monitoring.pdf']);
    ($this->monitoring)(['version_number' => 2, 'review_stage' => 'head', 'official_pdf_filename' => 'forwarded-monitoring.pdf']);
    ($this->monitoring)(['version_number' => 3, 'submission_status' => 'prepared', 'official_pdf_filename' => 'prepared-monitoring.pdf']);
    ($this->narrative)(['review_stage' => 'secretary', 'official_pdf_filename' => 'unforwarded-progress.pdf']);
    ($this->narrative)(['review_stage' => 'head', 'official_pdf_filename' => 'forwarded-progress.pdf']);
    ($this->narrative)(['submission_status' => 'prepared', 'official_pdf_filename' => 'prepared-progress.pdf']);
    ($this->narrative)(['report_type' => 'terminal', 'official_pdf_filename' => 'terminal.pdf']);
    $viewer = $workspace === 'faculty' ? $this->owner : User::factory()->create(['college' => 'Science']);
    $viewer->assignRole($workspace);
    $this->topic->update(['research_secretary_id' => $workspace === 'research_secretary' ? $viewer->id : null]);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $entries = ($this->readArchive)($viewer);
    $names = implode("\n", array_keys($entries));
    foreach ($included as $name) {
        expect($names)->toContain($name);
    }
    expect($names)->not->toContain('prepared-monitoring', 'prepared-progress');
    if ($workspace === 'research_head') {
        expect($names)->not->toContain('unforwarded');
    }
    if ($workspace === 'research_secretary') {
        expect($names)->not->toContain('terminal.pdf');
    }
})->with([
    ['faculty', ['unforwarded-monitoring.pdf', 'forwarded-monitoring.pdf', 'unforwarded-progress.pdf', 'forwarded-progress.pdf', 'terminal.pdf']],
    ['research_head', ['forwarded-monitoring.pdf', 'forwarded-progress.pdf', 'terminal.pdf']],
    ['research_secretary', ['unforwarded-monitoring.pdf', 'forwarded-monitoring.pdf', 'unforwarded-progress.pdf', 'forwarded-progress.pdf']],
]);

test('project archive keeps signed copies private until release and hides superseded copies', function () {
    $this->topic->update(['notice_to_proceed_issued_at' => null, 'status' => 'pending']);
    $version = ($this->version)();
    $file = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'position' => 1,
        'file_path' => ($this->store)('signed/private.pdf'), 'original_filename' => 'private-signed.pdf',
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
    ]);
    expect(implode('\n', array_keys(($this->readArchive)())))->not->toContain('private-signed.pdf');
    $this->topic->update(['notice_to_proceed_issued_at' => now(), 'status' => 'approved']);
    expect(implode('\n', array_keys(($this->readArchive)())))->toContain('private-signed.pdf');
    $file->update(['superseded_at' => now()]);
    expect(implode('\n', array_keys(($this->readArchive)())))->not->toContain('private-signed.pdf');
});

test('project archive includes submitted evidence and rejects evidence belonging to another project', function () {
    $path = ($this->store)('progress-reports/'.$this->topic->id.'/evidence/proof.pdf');
    $foreignPath = ($this->store)('progress-reports/999999/evidence/foreign.pdf');
    ($this->monitoring)(['work_plan' => [['evidence' => [
        ['id' => (string) Str::uuid(), 'path' => $path, 'name' => 'proof.pdf'],
        ['id' => (string) Str::uuid(), 'path' => $foreignPath, 'name' => 'foreign.pdf'],
        ['id' => (string) Str::uuid(), 'path' => 'progress-reports/'.$this->topic->id.'/evidence/missing.pdf', 'name' => 'missing-proof.pdf'],
    ]]]]);
    $entries = ($this->readArchive)();
    $names = implode('\n', array_keys($entries));
    expect($names)->toContain('/evidence/', 'proof.pdf')->not->toContain('foreign.pdf', 'missing-proof.pdf');
    expect($entries['INDEX.txt'])->toContain('missing-proof.pdf', 'unavailable');
});

test('legacy submitted monitoring PDFs are generated without saving another project file', function () {
    $report = ($this->monitoring)(['official_pdf_path' => null, 'work_plan' => [], 'budget_utilization' => []]);
    $this->mock(MonitoringToolDocumentService::class)->shouldReceive('generate')->once()->withArgs(fn ($candidate): bool => $candidate->id === $report->id)->andReturn('docx');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->with('docx')->andReturn('%PDF legacy monitoring');
    $before = Storage::disk('local')->allFiles();
    $entries = ($this->readArchive)();
    expect(implode('\n', $entries))->toContain('%PDF legacy monitoring', 'generated from submitted data')
        ->and(Storage::disk('local')->allFiles())->toBe($before);
});

test('archive preserves terminal report signed copies and figures with submitted narrative evidence', function () {
    $evidencePath = ($this->store)('narrative-progress-reports/'.$this->topic->id.'/evidence/progress-proof.pdf');
    ($this->narrative)(['accomplishments' => [['evidence' => [['path' => $evidencePath, 'name' => 'progress-proof.pdf']]]]]);
    $figurePath = ($this->store)('narrative-progress-reports/'.$this->topic->id.'/poster.jpg', 'poster-image');
    $signedPath = ($this->store)('narrative-progress-reports/'.$this->topic->id.'/signed/terminal.pdf');
    ($this->narrative)([
        'report_type' => 'terminal',
        'photos' => [['path' => $figurePath, 'original_name' => 'poster.jpg']],
        'terminal_data' => ['signed_copy' => ['path' => $signedPath, 'original_filename' => 'signed-terminal.pdf', 'checksum' => hash('sha256', '%PDF-1.7 official paper')]],
    ]);
    $entries = ($this->readArchive)();
    expect(implode('\n', array_keys($entries)))->toContain('progress-proof.pdf', 'poster.jpg', 'signed-terminal.pdf')
        ->and(implode('\n', $entries))->toContain('poster-image');
});

test('archives mark changed report PDFs as unavailable', function (string $kind, string $damage) {
    $report = $kind === 'monitoring' ? ($this->monitoring)() : ($this->narrative)(['report_type' => $kind === 'signed' ? 'terminal' : $kind]);
    $path = $report->official_pdf_path;
    $filename = $report->official_pdf_filename;
    if ($kind === 'signed') {
        $path = ($this->store)('narrative-progress-reports/'.$this->topic->id.'/signed/terminal.pdf');
        $filename = 'signed-terminal.pdf';
        $signed = ['path' => $path, 'original_filename' => $filename, 'checksum' => hash('sha256', '%PDF-1.7 official paper')];
        if ($damage === 'unchecked') {
            unset($signed['checksum']);
        }
        $report->update(['terminal_data' => ['signed_copy' => $signed]]);
    } elseif ($damage === 'unchecked') {
        $report->update(['official_pdf_checksum' => null]);
    }
    if ($damage !== 'unchecked') {
        Storage::disk('local')->put($path, $damage === 'empty' ? '' : '%PDF-1.7 altered official report');
    }

    $entries = ($this->readArchive)();
    $manifest = json_decode($entries['manifest.json'], true, flags: JSON_THROW_ON_ERROR);
    $file = collect($manifest['files'])->firstWhere('filename', $filename);
    expect($file['status'])->toBe('unavailable')
        ->and($entries)->not->toHaveKey($file['archive_path'])
        ->and($manifest['missing_files'])->toBe(1)
        ->and($entries['INDEX.txt'])->toContain('INCOMPLETE');
})->with(['monitoring', 'progress', 'terminal', 'signed'])->with(['replaced', 'empty', 'unchecked']);

test('unsafe report paths do not abort the project archive', function (string $kind, string $path) {
    if ($kind === 'signed') {
        ($this->narrative)(['report_type' => 'terminal', 'terminal_data' => ['signed_copy' => [
            'path' => $path, 'original_filename' => 'unsafe-report.pdf', 'checksum' => hash('sha256', 'unsafe report'),
        ]]]);
    } else {
        ($kind === 'monitoring' ? $this->monitoring : $this->narrative)([
            'official_pdf_path' => $path, 'official_pdf_filename' => 'unsafe-report.pdf',
        ]);
    }
    $entries = ($this->readArchive)();
    $manifest = json_decode($entries['manifest.json'], true, flags: JSON_THROW_ON_ERROR);
    $file = collect($manifest['files'])->firstWhere('filename', 'unsafe-report.pdf');
    expect($file['status'])->toBe('unavailable')
        ->and($manifest['missing_files'])->toBe(1)
        ->and($entries)->not->toHaveKey($file['archive_path'])
        ->and($entries['INDEX.txt'])->toContain('INCOMPLETE');
})->with(['monitoring', 'progress', 'signed'])->with(['../outside.pdf', 'C:\\private\\report.pdf']);

test('archive construction removes its temporary zip when storage fails', function () {
    $this->topic->projectDocuments()->create([
        'uploaded_by' => $this->owner->id, 'category' => 'supporting_files', 'title' => 'Reference',
        'file_path' => ($this->store)('project-documents/'.$this->topic->id.'/reference.pdf'),
        'original_filename' => 'reference.pdf', 'mime_type' => 'application/pdf', 'file_size' => 24,
    ]);
    $disk = Mockery::mock(Storage::disk('local'));
    $disk->shouldReceive('mimeType')->andThrow(new RuntimeException('Storage unavailable'));
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $before = glob(sys_get_temp_dir().'/athena-project-archive-*');

    expect(fn () => app(ProjectArchive::class)->download($this->topic->fresh(), $this->owner))->toThrow(RuntimeException::class, 'Storage unavailable');
    expect(glob(sys_get_temp_dir().'/athena-project-archive-*'))->toBe($before);
});

test('archive download rejects unrelated users and deletes its temporary zip after sending', function (int $status) {
    ($this->version)();
    $stranger = User::factory()->create();
    $stranger->assignRole('faculty');
    $this->actingAs($stranger)->get(route('topics.archive', $this->topic))->assertForbidden();
    $response = $this->actingAs($this->owner)->get(route('topics.archive', $this->topic))->assertOk()->assertDownload();
    $path = $response->baseResponse->getFile()->getPathname();
    expect(is_file($path))->toBeTrue();
    $response->baseResponse->setStatusCode($status);
    ob_start();
    try {
        $response->baseResponse->sendContent();
    } finally {
        ob_end_clean();
    }
    expect(is_file($path))->toBeFalse();
})->with([200, 500]);
