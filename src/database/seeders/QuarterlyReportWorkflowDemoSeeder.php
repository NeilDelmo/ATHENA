<?php

namespace Database\Seeders;

use App\Contracts\DocumentPdfConverter;
use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectConference;
use App\Models\ProjectDocument;
use App\Models\ProjectJournalSubmission;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\RmsQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use App\Services\MonitoringToolDocumentService;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use App\Services\ProgressReportDocumentService;
use App\Services\RmsQuarterlyReportData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class QuarterlyReportWorkflowDemoSeeder extends Seeder
{
    private const MARKER = 'quarterly-workflow-demo';

    /** @var list<string> */
    private array $storedPaths = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Quarterly workflow samples may only be added in local or testing environments.');
        }
        $coordinator = User::role('research_coordinator')->whereNotNull('college')->where('college', '!=', '')->orderBy('id')->firstOrFail();
        $head = User::role('research_head')->orderBy('id')->firstOrFail();
        $secretary = User::role('research_secretary')->where('college', $coordinator->college)->orderBy('id')->firstOrFail();
        $faculty = User::role('faculty_researcher')->where('college', $coordinator->college)->orderBy('id')->limit(2)->get();
        if ($faculty->count() !== 2) {
            throw new RuntimeException('The quarterly sample needs two existing Faculty Researchers in the coordinator college.');
        }
        $existing = CollegeQuarterlyReport::where('college', $coordinator->college)->where('reporting_year', 2026)->where('reporting_quarter', 3)->first();
        if (($existing?->snapshot['demo_seed'] ?? null) === self::MARKER) {
            $this->command?->info('The Q3 2026 workflow sample already exists. All later edits were preserved.');

            return;
        }
        if (CollegeQuarterlyReport::where('college', $coordinator->college)->where('reporting_year', 2026)->whereIn('reporting_quarter', [3, 4])->exists()
            || RmsQuarterlyReport::where('reporting_year', 2026)->where('reporting_quarter', 3)->exists()) {
            throw new RuntimeException('The sample reporting period already contains a report. Existing reports were left unchanged.');
        }
        $this->storedPaths = [];
        try {
            DB::transaction(function () use ($coordinator, $head, $secretary, $faculty): void {
                $projects = [];
                foreach (['Flood Sensor Network', 'Student Advising Analytics'] as $index => $title) {
                    $projects[] = $this->facultyProject($faculty[$index], $head, $secretary, $title, $index);
                }
                $report = $this->collegeReport($coordinator, $head, $projects);
                $this->rmsReport($head, $report, $projects);
                CollegeQuarterlyReport::create([
                    'college' => $coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 4,
                    'prepared_by' => $coordinator->id,
                    'other_accomplishments' => 'DEMO ONLY: Editable Q4 draft. Review the refreshed faculty research and add verified activities before submission opens on January 1, 2027.',
                    'presentations' => [], 'publications' => [], 'seminars' => [], 'manual_research' => [], 'ip_assets' => [], 'evidence' => [],
                ]);
                foreach ($projects as $project) {
                    $this->command?->info('Faculty: '.$project->user->name.' - project #'.$project->id.' '.$project->title);
                }
                $this->command?->info('Coordinator: '.$coordinator->name.' - Q3 2026 submitted college report #'.$report->id.'; Q4 2026 editable draft.');
                $this->command?->info('Research Head: '.$head->name.' - Q3 2026 RMS draft with presentation, publication and IP asset.');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($this->storedPaths);
            throw $exception;
        }
    }

    private function facultyProject(User $faculty, User $head, User $secretary, string $title, int $index): TopicProposal
    {
        $project = TopicProposal::create([
            'user_id' => $faculty->id, 'title' => '[DEMO] '.$title,
            'description' => '['.self::MARKER.'] Fictional Q3 2026 reporting example. Start with faculty reports, follow secretary checking, then the college accomplishment report and campus RMS export. No real research results or approvals are claimed.',
            'estimated_budget' => 75000, 'estimated_duration_months' => 12, 'status' => 'approved', 'project_status' => 'ongoing',
            'notice_to_proceed_issued_by' => $head->id, 'notice_to_proceed_issued_at' => '2026-01-01 09:00:00',
            'notice_to_proceed_data' => ['notice_date' => '2026-01-01', 'project_title' => '[DEMO ONLY] '.$title,
                'researcher_names' => [$faculty->name], 'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31',
                'approved_duration_months' => 12, 'approved_budget' => '75000.00', 'resolution_number' => 'DEMO-NOT-AN-APPROVAL', 'resolution_year' => 2026],
        ]);
        $notice = app(NoticeToProceedDataService::class);
        $noticePdf = app(DocumentPdfConverter::class)->convertDocx(app(NoticeToProceedDocumentService::class)->generate($notice->documentValues($notice->defaults($project))));
        $noticePath = 'quarterly-workflow-demo/'.$project->id.'/DEMO-notice-to-proceed.pdf';
        $this->store($noticePath, $noticePdf);
        $project->update(['notice_to_proceed_path' => $noticePath, 'notice_to_proceed_original_filename' => 'DEMO-notice-to-proceed.pdf']);
        $proof = $this->proof('progress-reports/'.$project->id.'/evidence/'.Str::uuid().'.png', $title.' - pilot validation');
        $progress = $index === 0 ? 60 : 80;
        $row = ['objective' => 'DEMO: Evaluate the '.$title.' prototype.', 'activity' => 'DEMO: Pilot validation', 'percent_weight' => 100,
            'physical_target' => 'DEMO: 20 pilot sessions', 'target_completion_date' => '2026-12-31',
            'actual_accomplishment' => 'DEMO: '.($index === 0 ? 12 : 16).' of 20 fictional pilot sessions recorded.',
            'accomplished_percentage' => $progress, 'findings' => 'DEMO: Remaining pilot sessions are scheduled for Q4.', 'evidence' => [$proof]];
        $review = ['review_stage' => 'head', 'review_status' => 'reviewed',
            'secretary_remarks' => 'DEMO ONLY: Checked the report and attached pilot evidence; forwarded to the Research Head.',
            'secretary_reviewed_by' => $secretary->id, 'secretary_reviewed_at' => '2026-10-02 09:00:00', 'forwarded_at' => '2026-10-02 09:00:00',
            'research_head_remarks' => 'DEMO ONLY: Quarterly report accepted. Continue the approved work plan; this is not project completion.',
            'reviewed_by' => $head->id, 'reviewed_at' => '2026-10-03 09:00:00'];
        $monitoring = ProjectProgressReport::create([
            'topic_id' => $project->id, 'submitted_by' => $faculty->id, 'reporting_date' => '2026-09-30',
            'reporting_year' => 2026, 'reporting_quarter' => 3, 'period_start' => '2026-07-01', 'period_end' => '2026-09-30',
            'version_number' => 1, 'tracking_number' => 'DEMO-Q3-'.$project->id.'-MONITORING',
            'progress_percentage' => $progress, 'accomplishments' => $row['actual_accomplishment'], 'issues' => 'DEMO: Q4 pilot activities remain outstanding.',
            'work_plan' => [$row], 'budget_utilization' => [['type' => 'Purchase Request', 'details' => 'DEMO prototype supplies', 'amount_requested' => 25000, 'actual_amount' => 20000, 'remarks' => 'Fictional amounts only.']],
            'budget_prepared_by' => $faculty->id, 'budget_prepared_at' => '2026-10-01 08:00:00',
            'prepared_at' => '2026-10-01 08:00:00', 'submitted_at' => '2026-10-01 09:00:00', 'submission_status' => 'submitted', ...$review,
        ]);
        $this->reportPdf($monitoring);
        $narrative = ProjectNarrativeReport::create([
            'topic_id' => $project->id, 'submitted_by' => $faculty->id, 'report_type' => 'progress',
            'submission_date' => '2026-10-01', 'reporting_date' => '2026-09-30', 'reporting_quarter' => 3,
            'period_start' => '2026-07-01', 'period_end' => '2026-09-30', 'version_number' => 1,
            'tracking_number' => 'DEMO-Q3-'.$project->id.'-PROGRESS', 'researchers' => $faculty->name,
            'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31', 'budget' => 75000, 'funding_agency' => 'DEMO institutionally funded project',
            'accomplishment_summary' => $row['actual_accomplishment'],
            'accomplishments' => [['objective' => $row['objective'], 'target' => $row['physical_target'], 'actual' => $row['actual_accomplishment']]],
            'introduction' => 'DEMO ONLY: '.$title.' illustrates how faculty progress contributes to college reporting.',
            'rationale' => 'DEMO ONLY: A fictional prototype evaluation used to demonstrate the workflow.',
            'objectives' => $row['objective'], 'methodology' => 'DEMO ONLY: Simulated pilot sessions; no real participants or findings.',
            'results_discussion' => $row['actual_accomplishment'].' Further work is planned for Q4.', 'photos' => [],
            'prepared_at' => '2026-10-01 08:00:00', 'submitted_at' => '2026-10-01 09:00:00', 'submission_status' => 'submitted',
            ...($index === 0 ? $review : ['review_stage' => 'secretary', 'review_status' => 'pending']),
        ]);
        $this->reportPdf($narrative);
        $draft = $monitoring->replicate(['official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size', 'secretary_remarks',
            'secretary_reviewed_by', 'secretary_reviewed_at', 'forwarded_at', 'research_head_remarks', 'reviewed_by', 'reviewed_at', 'submitted_at']);
        $draft->fill(['reporting_date' => '2026-12-31', 'reporting_quarter' => 4, 'period_start' => '2026-10-01', 'period_end' => '2026-12-31',
            'tracking_number' => 'DEMO-Q4-'.$project->id.'-MONITORING', 'submission_status' => 'prepared', 'review_stage' => 'secretary', 'review_status' => 'pending',
            'prepared_at' => now(), 'work_plan' => [[...$row, 'evidence' => [], 'actual_accomplishment' => 'DEMO: Editable draft; enter verified Q4 progress.']],
            'accomplishments' => 'DEMO: Editable Q4 draft. Review before preparing the final report.', 'budget_prepared_by' => null, 'budget_prepared_at' => null])->save();
        $this->reportPdf($draft);

        return $project;
    }

    /** @param list<TopicProposal> $projects */
    private function collegeReport(User $coordinator, User $head, array $projects): CollegeQuarterlyReport
    {
        $report = CollegeQuarterlyReport::create(['college' => $coordinator->college, 'reporting_year' => 2026, 'reporting_quarter' => 3,
            'prepared_by' => $coordinator->id, 'other_accomplishments' => 'DEMO ONLY: Two faculty examples show quarterly monitoring, dissemination and IP reporting. The draft narrative for Student Advising Analytics is still awaiting Research Office Secretary checking; its separate Monitoring Tool has already been checked and accepted.',
            'reviewed_by' => $head->name, 'reviewed_by_title' => 'Research Head (demonstration)',
            'manual_research' => [], 'evidence' => [], 'submitted_at' => '2026-10-05 09:00:00']);
        $activities = [
            'presentations' => ['id' => (string) Str::uuid(), 'event' => 'DEMO Campus Research Forum', 'research_title' => $projects[0]->title, 'date' => '2026-08-20', 'place' => 'DEMO ARASOF-Nasugbu venue', 'personnel' => $projects[0]->user->name, 'project_id' => $projects[0]->id],
            'publications' => ['id' => (string) Str::uuid(), 'event' => 'DEMO Journal of Student Computing', 'research_title' => $projects[1]->title, 'date' => '2026-09-15', 'personnel' => $projects[1]->user->name, 'project_id' => $projects[1]->id],
            'seminars' => ['id' => (string) Str::uuid(), 'event' => 'DEMO Research Data Management Workshop', 'research_title' => '', 'date' => '2026-09-10', 'place' => 'DEMO campus training room', 'personnel' => $projects[0]->user->name.'; '.$projects[1]->user->name, 'project_id' => $projects[0]->id],
        ];
        $evidence = [];
        $documents = [];
        foreach ($activities as $section => $row) {
            $project = $section === 'publications' ? $projects[1] : $projects[0];
            $proof = $this->proof('project-documents/'.$project->id.'/quarterly-workflow-demo/'.$section.'.png', $row['event']);
            $document = $project->projectDocuments()->create(['uploaded_by' => $coordinator->id, 'category' => ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE,
                'title' => $row['event'].' - DEMO proof', 'note' => 'DEMO ONLY: Fictional evidence for Q3 2026. Not a real certificate or publication.',
                'file_path' => $proof['path'], 'original_filename' => $proof['name'], 'mime_type' => 'image/png', 'file_size' => $proof['size'], 'checksum' => $proof['checksum']]);
            $documents[$section] = route('topics.documents.view', [$project, $document]);
            $evidence[] = [...$proof, 'caption' => 'DEMO ONLY: '.$row['event'], 'original_filename' => $proof['name'],
                'section' => $section, 'row_id' => $row['id'], 'folder' => $row['research_title'] ?: $row['event'], 'project_id' => $project->id, 'document_id' => $document->id];
            $report->{$section} = [$row];
        }
        $ipProof = $this->proof('project-documents/'.$projects[1]->id.'/quarterly-workflow-demo/ip-submission.png', 'DEMO Copyright Submission Acknowledgment');
        $ipDocument = $projects[1]->projectDocuments()->create(['uploaded_by' => $coordinator->id, 'category' => ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE,
            'title' => 'DEMO Copyright Submission Acknowledgment', 'note' => 'DEMO ONLY: Fictional submission acknowledgment. No IP registration is claimed.',
            'file_path' => $ipProof['path'], 'original_filename' => $ipProof['name'], 'mime_type' => 'image/png', 'file_size' => $ipProof['size'], 'checksum' => $ipProof['checksum']]);
        $evidence[] = [...$ipProof, 'caption' => 'DEMO ONLY: Copyright submission acknowledgment', 'original_filename' => $ipProof['name'], 'project_id' => $projects[1]->id, 'document_id' => $ipDocument->id];
        $report->ip_assets = [['id' => (string) Str::uuid(), 'title' => '[DEMO] Student Advising Analytics Software', 'type' => 'Copyright',
            'inventors' => $projects[1]->user->name, 'date' => '2026-09-25', 'remarks' => 'DEMO ONLY: Fictional copyright submission; no registration is claimed.', 'evidence_url' => route('topics.documents.view', [$projects[1], $ipDocument])]];
        $service = app(CollegeQuarterlyReportFacultyEvidence::class);
        $report->evidence = [...$evidence, ...$service->freeze($report, $service->forQuarter($coordinator, 2026, 3), $this->storedPaths)];
        $report->snapshot = [...app(CollegeQuarterlyReportData::class)->build($coordinator, 2026, 3), 'demo_seed' => self::MARKER,
            'presentations' => $report->activityRows('presentations'), 'publications' => $report->activityRows('publications'),
            'manual_presentations' => $report->presentations, 'manual_publications' => $report->publications, 'manual_research' => [], 'seminars' => $report->seminars,
            'ip_assets' => $report->ip_assets, 'other_accomplishments' => $report->other_accomplishments,
            'reviewed_by' => $report->reviewed_by, 'reviewed_by_title' => $report->reviewed_by_title, 'prepared_by_name' => $coordinator->name, 'college' => $coordinator->college, 'evidence' => $report->evidence];
        $report->save();
        $report->submissions()->create(['version_number' => 1, 'submitted_by' => $coordinator->id, 'snapshot' => $report->snapshot, 'submitted_at' => $report->submitted_at]);
        ProjectConference::create(['topic_id' => $projects[0]->id, 'added_by' => $projects[0]->user_id, 'fingerprint' => hash('sha256', self::MARKER.'-presentation'),
            'title' => $activities['presentations']['event'], 'url' => $documents['presentations'], 'status' => 'presented', 'event_date' => '2026-08-20', 'presented_on' => '2026-08-20',
            'evidence_url' => $documents['presentations'], 'location' => $activities['presentations']['place'], 'notes' => 'DEMO ONLY: Fictional presentation. Entered manually by the coordinator in the college report.']);
        ProjectJournalSubmission::create(['topic_id' => $projects[1]->id, 'added_by' => $projects[1]->user_id, 'fingerprint' => hash('sha256', self::MARKER.'-publication'),
            'journal_name' => $activities['publications']['event'], 'manuscript_title' => $projects[1]->title, 'journal_url' => $documents['publications'],
            'status' => 'published', 'published_on' => '2026-09-15', 'publication_url' => $documents['publications'], 'notes' => 'DEMO ONLY: Fictional journal and publication; not an actual research output.']);

        return $report;
    }

    /** @param list<TopicProposal> $projects */
    private function rmsReport(User $head, CollegeQuarterlyReport $report, array $projects): void
    {
        $data = app(RmsQuarterlyReportData::class)->build(2026, 3);
        $details = [];
        foreach (['presentations', 'publications', 'ip_assets'] as $section) {
            foreach ($data[$section] as $row) {
                if ($row['report_id'] !== $report->id) {
                    continue;
                }
                $proof = collect($report->evidence)->firstWhere('section', $section);
                $values = [...$row['values'], 'evidence_url' => $section === 'ip_assets'
                    ? $report->ip_assets[0]['evidence_url'] : route('topics.documents.view', [$proof['project_id'], $proof['document_id']])];
                if ($section !== 'ip_assets') {
                    $values = [...$values, 'authors' => $projects[$section === 'presentations' ? 0 : 1]->user->name,
                        'source_of_fund' => 'Institutionally-funded', 'project_status' => 'On-going', 'sdgs' => $section === 'presentations' ? '9, 11, 13' : '4, 9', 'support_requested' => 'No'];
                }
                if ($section === 'presentations') {
                    $values = [...$values, 'classification' => 'Institutional', 'theme' => 'DEMO: Research for campus communities', 'organizer' => 'DEMO Research Office'];
                } elseif ($section === 'publications') {
                    $values = [...$values, 'publication_type' => 'Journal', 'indexing' => 'Others', 'volume_issue' => 'DEMO Vol. 1, Issue 1', 'pages' => '1-8', 'publisher' => 'DEMO publisher (fictional)'];
                }
                $details[$row['key']] = $values;
            }
        }
        RmsQuarterlyReport::create(['reporting_year' => 2026, 'reporting_quarter' => 3, 'campus' => 'ARASOF-Nasugbu', 'updated_by' => $head->id,
            'row_details' => $details, 'signatories' => ['prepared' => ['name' => $head->name, 'position' => 'Research Head', 'office' => 'DEMO Research Office'],
                'reviewed' => ['name' => 'DEMO Reviewer', 'position' => 'Sample reviewer', 'office' => 'Demonstration only'],
                'approved' => ['name' => 'DEMO Approver', 'position' => 'Sample approver', 'office' => 'Demonstration only']]]);
    }

    private function reportPdf(ProjectProgressReport|ProjectNarrativeReport $report): void
    {
        $service = $report instanceof ProjectProgressReport ? MonitoringToolDocumentService::class : ProgressReportDocumentService::class;
        $pdf = app(DocumentPdfConverter::class)->convertDocx(app($service)->generate($report));
        $path = 'quarterly-workflow-demo/'.$report->topic_id.'/'.$report->tracking_number.'.pdf';
        $this->store($path, $pdf);
        $report->update(['official_pdf_path' => $path, 'official_pdf_filename' => basename($path), 'official_pdf_checksum' => hash('sha256', $pdf), 'official_pdf_size' => strlen($pdf)]);
    }

    /** @return array{id: string, path: string, name: string, mime_type: string, size: int, checksum: string} */
    private function proof(string $path, string $label): array
    {
        $image = imagecreatetruecolor(1000, 360);
        imagefill($image, 0, 0, imagecolorallocate($image, 248, 250, 252));
        $ink = imagecolorallocate($image, 30, 41, 59);
        imagestring($image, 5, 35, 40, 'ATHENA - DEMONSTRATION ONLY', imagecolorallocate($image, 128, 0, 0));
        imagestring($image, 5, 35, 105, $label, $ink);
        imagestring($image, 4, 35, 165, 'Q3 2026 | Fictional evidence for the reporting workflow sample.', $ink);
        imagestring($image, 4, 35, 210, 'No actual attendance, publication, IP registration or findings are certified.', $ink);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        $this->store($path, $contents);

        return ['id' => (string) Str::uuid(), 'path' => $path, 'name' => 'DEMO-'.Str::slug($label).'.png', 'mime_type' => 'image/png', 'size' => strlen($contents), 'checksum' => hash('sha256', $contents)];
    }

    private function store(string $path, string $contents): void
    {
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Unable to store the quarterly workflow sample file.');
        }
        $this->storedPaths[] = $path;
    }
}
