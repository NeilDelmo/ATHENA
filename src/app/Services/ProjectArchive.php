<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class ProjectArchive
{
    public function download(TopicProposal $topic, User $viewer): BinaryFileResponse
    {
        Gate::forUser($viewer)->authorize('view', $topic);
        $topic->loadMissing(['versions.files', 'projectDocuments', 'progressReports', 'narrativeReports', 'user']);
        $path = tempnam(sys_get_temp_dir(), 'athena-project-archive-');
        if ($path === false) {
            throw new RuntimeException('A temporary project archive could not be created.');
        }

        $zip = new ZipArchive;
        $open = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The project archive could not be opened.');
            }
            $open = true;
            $files = [];
            foreach ($this->records($topic, $viewer) as $record) {
                $files[] = $this->append($zip, $record);
            }
            $missing = count(array_filter($files, fn (array $file): bool => $file['status'] !== 'included'));
            $manifest = [
                'project' => ['id' => $topic->id, 'title' => $topic->title, 'status' => $topic->status],
                'generated_at' => now()->toIso8601String(),
                'scope' => 'Submitted project records available to the downloading user. Drafts, prepared reports, and restricted records are excluded.',
                'missing_files' => $missing,
                'files' => $files,
            ];
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR))
                || ! $zip->addFromString('INDEX.txt', $this->index($manifest))) {
                throw new RuntimeException('The project archive index could not be written.');
            }
            $closed = $zip->close();
            $open = false;
            if (! $closed) {
                throw new RuntimeException('The project archive could not be saved.');
            }
            clearstatcache(true, $path);

            return response()->download($path, 'project-'.$topic->id.'-'.Str::limit(Str::slug($topic->title) ?: 'research', 80, '').'-archive.zip', [
                'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if ($open) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    private function records(TopicProposal $topic, User $viewer): array
    {
        $records = [];
        foreach ($topic->versions as $version) {
            $folder = 'version-'.$version->version_number.'-'.$version->id;
            $edition = 'Version '.$version->version_number;
            foreach ($version->files as $file) {
                $signed = $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
                    && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED;
                if ($signed && ! $viewer->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                    && ! $viewer->can('viewSigningDocuments', $topic)
                    && (! $topic->hasIssuedNoticeToProceed() || $file->isSuperseded())) {
                    continue;
                }
                $records[] = $this->record(
                    ($signed ? '02-signed-documents/' : '01-proposal-papers/').$folder,
                    'file-'.$file->id, $file->original_filename, $file->file_path,
                    $signed ? 'Signed proposal document' : $file->label(), $edition,
                    $file->created_at, $file->isSuperseded() ? 'Superseded' : 'Submitted',
                );
            }
            if (! $version->files->contains('file_path', $version->file_path)) {
                $records[] = $this->record('01-proposal-papers/'.$folder, 'primary', $version->original_filename, $version->file_path, 'Detailed proposal', $edition, $version->created_at);
            }
        }
        if ($topic->versions->isEmpty()) {
            foreach (['Initial' => $topic->initial_file_path, 'Final' => $topic->final_file_path] as $edition => $path) {
                if (filled($path)) {
                    $records[] = $this->record('01-proposal-papers/legacy', strtolower($edition), basename(str_replace('\\', '/', $path)), $path, 'Detailed proposal', $edition, $topic->created_at);
                }
            }
        }
        if (filled($topic->signed_approval_path)) {
            $records[] = $this->record('02-signed-documents', 'approval', 'signed-approval.pdf', $topic->signed_approval_path, 'Signed approval', 'Project approval', $topic->updated_at);
        }
        if ($topic->notice_to_proceed_issued_at !== null) {
            $records[] = $this->record('03-notice-to-proceed', 'notice', $topic->notice_to_proceed_original_filename ?: 'notice-to-proceed.pdf', $topic->notice_to_proceed_path, 'Notice to Proceed', 'Official release', $topic->notice_to_proceed_issued_at, 'Released');
        }
        foreach ($topic->projectDocuments as $document) {
            $records[] = [...$this->record('07-supporting-files', 'document-'.$document->id, $document->original_filename, $document->file_path, $document->categoryLabel(), 'Project supporting file', $document->created_at),
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/png']];
        }
        foreach ($topic->progressReports->sortBy('id') as $report) {
            if (! $this->canReadReport($topic, $viewer, $report)) {
                continue;
            }
            $folder = '04-monitoring-reports/report-'.$report->id.'-v'.($report->version_number ?: 1);
            $record = $this->record($folder, 'official', $report->official_pdf_filename ?: 'monitoring-tool.pdf', $report->official_pdf_path, 'Monitoring Tool', 'Version '.($report->version_number ?: 1), $report->submitted_at ?? $report->created_at);
            $record['period'] = $report->reporting_period_label;
            $record['checksum'] = $report->official_pdf_checksum;
            if (blank($report->official_pdf_path) && is_array($report->work_plan) && is_array($report->budget_utilization)) {
                $record['generate'] = fn (): string => app(DocumentPdfConverter::class)->convertDocx(app(MonitoringToolDocumentService::class)->generate($report));
            }
            $records[] = $record;
            if (filled($report->attachment_path)) {
                $records[] = $this->record($folder.'/evidence', 'attachment', basename(str_replace('\\', '/', $report->attachment_path)), $report->attachment_path, 'Monitoring attachment', $record['edition'], $report->submitted_at ?? $report->created_at);
            }
            array_push($records, ...$this->evidence($topic, $report->work_plan ?? [], $folder, 'progress-reports', $record));
        }
        foreach ($topic->narrativeReports->sortBy('id') as $report) {
            if (! $this->canReadReport($topic, $viewer, $report)) {
                continue;
            }
            $folder = ($report->report_type === 'terminal' ? '06-terminal-reports/' : '05-progress-reports/').'report-'.$report->id.'-v'.($report->version_number ?: 1);
            $record = $this->record($folder, 'official', $report->official_pdf_filename ?: 'project-report.pdf', $report->official_pdf_path, $report->report_label, 'Version '.($report->version_number ?: 1), $report->submitted_at ?? $report->created_at);
            $record['period'] = $report->reporting_period_label ?: $report->submission_date?->toDateString();
            $record['checksum'] = $report->official_pdf_checksum;
            if (blank($report->official_pdf_path)) {
                $record['generate'] = fn (): string => app(DocumentPdfConverter::class)->convertDocx(app(ProgressReportDocumentService::class)->generate($report));
            }
            $records[] = $record;
            array_push($records, ...$this->evidence($topic, $report->accomplishments ?? [], $folder, 'narrative-progress-reports', $record));
            foreach ($report->photos ?? [] as $index => $photo) {
                if (is_array($photo)) {
                    $records[] = [...$this->record($folder.'/evidence', 'figure-'.$index, $photo['original_name'] ?? 'figure', $photo['path'] ?? null, 'Report figure', $record['edition'], $report->submitted_at ?? $report->created_at), 'prefix' => 'narrative-progress-reports/'.$topic->id.'/'];
                }
            }
            if (($signed = $report->signedCopy()) !== null) {
                $records[] = [...$this->record($folder, 'signed', $signed['original_filename'] ?? 'signed-report.pdf', $signed['path'], 'Signed '.$report->report_label, $record['edition'], $report->updated_at),
                    'checksum' => $signed['checksum'] ?? null];
            }
        }

        return $records;
    }

    private function canReadReport(TopicProposal $topic, User $viewer, ProjectProgressReport|ProjectNarrativeReport $report): bool
    {
        if (! $report->isSubmitted()) {
            return false;
        }
        if ($viewer->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return $report->isAvailableToResearchHead();
        }
        if ($viewer->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)) {
            return $topic->canReviewImplementationReports($viewer)
                && (! $report instanceof ProjectNarrativeReport || $report->report_type === 'progress');
        }

        return $topic->isAccessibleTo($viewer);
    }

    /** @param array<int, mixed> $rows @param array<string, mixed> $report @return list<array<string, mixed>> */
    private function evidence(TopicProposal $topic, array $rows, string $folder, string $directory, array $report): array
    {
        $records = [];
        foreach ($rows as $rowIndex => $row) {
            foreach (is_array($row) && is_array($row['evidence'] ?? null) ? $row['evidence'] : [] as $index => $file) {
                if (is_array($file)) {
                    $records[] = [...$this->record($folder.'/evidence', 'row-'.$rowIndex.'-'.$index, $file['name'] ?? 'evidence', $file['path'] ?? null, 'Report supporting evidence', $report['edition'], null),
                        'date' => $report['date'], 'prefix' => $directory.'/'.$topic->id.'/evidence/'];
                }
            }
        }

        return $records;
    }

    /** @return array<string, mixed> */
    private function record(string $folder, string $id, ?string $filename, ?string $path, string $category, string $edition, ?CarbonInterface $date, string $recordStatus = 'Submitted'): array
    {
        $filename = $this->filename($filename ?: 'document');

        return ['folder' => $folder, 'id' => $this->filename($id), 'filename' => $filename, 'path' => $path,
            'category' => $category, 'edition' => $edition, 'date' => $date?->toIso8601String(), 'record_status' => $recordStatus];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function append(ZipArchive $zip, array $record): array
    {
        $entry = array_intersect_key($record, array_flip(['filename', 'category', 'edition', 'date', 'record_status', 'period']));
        $entry['archive_path'] = $record['folder'].'/'.$record['id'].'-'.$record['filename'];
        $entry['status'] = 'unavailable';
        if (isset($record['generate'])) {
            try {
                $contents = $record['generate']();
            } catch (Throwable $exception) {
                report($exception);
                $entry['note'] = 'The PDF could not be generated from submitted data.';

                return $entry;
            }
            if (! $zip->addFromString($entry['archive_path'], $contents)) {
                throw new RuntimeException('A generated report could not be added to the project archive.');
            }

            return [...$entry, 'status' => 'included', 'note' => 'PDF generated from submitted data.', 'size' => strlen($contents), 'sha256' => hash('sha256', $contents)];
        }
        $path = $record['path'];
        if (! is_string($path) || $path === '' || preg_match('~(?:^/|[\\\\:\x00]|(?:^|/)\.\.(?:/|$))~', $path)
            || (isset($record['prefix']) && ! str_starts_with($path, $record['prefix']))) {
            return [...$entry, 'note' => 'Stored file path is missing or unsafe; file unavailable.'];
        }
        $root = realpath(Storage::disk('local')->path(''));
        $absolute = realpath(Storage::disk('local')->path($path));
        if ($absolute === false || ! is_file($absolute) || ! is_readable($absolute)) {
            return [...$entry, 'note' => 'Stored file is missing or unreadable; file unavailable.'];
        }
        if ($root === false || ! str_starts_with(strtolower(str_replace('\\', '/', $absolute)), strtolower(rtrim(str_replace('\\', '/', $root), '/').'/'))) {
            return [...$entry, 'note' => 'Stored file resolves outside project storage; unsafe file unavailable.'];
        }
        if (isset($record['mime_types']) && ! in_array(Storage::disk('local')->mimeType($path), $record['mime_types'], true)) {
            return [...$entry, 'note' => 'Stored file format is unavailable for download.'];
        }
        $size = filesize($absolute);
        $checksum = hash_file('sha256', $absolute);
        if (array_key_exists('checksum', $record)
            && ($size === 0 || ! is_string($record['checksum']) || ! hash_equals($record['checksum'], $checksum))) {
            return [...$entry, 'note' => 'Stored report file is empty or could not be verified; file unavailable.'];
        }
        if (! $zip->addFile($absolute, $entry['archive_path'])) {
            throw new RuntimeException('A stored file could not be added to the project archive.');
        }

        return [...$entry, 'status' => 'included', 'size' => $size, 'sha256' => $checksum];
    }

    private function filename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($filename)) ?? '';

        return substr(trim(preg_replace('/\.{2,}/', '.', $filename) ?? '', '.-'), 0, 160) ?: 'document';
    }

    /** @param array<string, mixed> $manifest */
    private function index(array $manifest): string
    {
        $lines = ['PROJECT ARCHIVE', 'Project #'.$manifest['project']['id'].': '.$manifest['project']['title'],
            'Generated: '.$manifest['generated_at'], $manifest['scope'],
            $manifest['missing_files'] > 0 ? 'INCOMPLETE: '.$manifest['missing_files'].' recorded files are unavailable. See entries below.' : 'No missing files among the records available to this user.',
            'The manifest lists each edition, date, archive location, size, and SHA-256 checksum.'];
        foreach ($manifest['files'] as $file) {
            $lines[] = "\n".strtoupper($file['status']).' | '.$file['category'].' | '.$file['edition'].' | '.$file['record_status'].' | '.($file['date'] ?? 'Date not recorded');
            $lines[] = $file['archive_path'];
            if (filled($file['period'] ?? null)) {
                $lines[] = 'Reporting period: '.$file['period'];
            }
            if (filled($file['note'] ?? null)) {
                $lines[] = $file['note'];
            }
        }

        return implode("\n", $lines)."\n";
    }
}
