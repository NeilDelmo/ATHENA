<?php

use App\Models\ProjectDocument;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Services\ProjectDocumentLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    Storage::fake('local');
    $this->withoutVite();

    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Clean Water Research',
        'estimated_budget' => 25_000,
        'estimated_duration_months' => 8,
        'status' => 'pending',
    ]);
    $this->makeResponseVersion = function (int $number = 1) {
        $path = 'proposal-packages/response-version-'.$number.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7 proposal');
        $version = $this->topic->versions()->create([
            'submitted_by' => $this->faculty->id, 'version_number' => $number, 'submission_type' => $number === 1 ? 'initial' : 'revision',
            'title' => $this->topic->title, 'file_path' => $path, 'original_filename' => 'proposal.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 18, 'checksum' => str_repeat('a', 64),
        ]);
        $paper = $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'position' => 0,
            'file_path' => $path, 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 18, 'checksum' => str_repeat('b', 64),
        ]);

        return [$version, $paper];
    };
});

test('an empty project folder keeps its Explorer browse surface and View menu', function () {
    $view = $this->actingAs($this->faculty)->get(route('topics.show', $this->topic))->assertOk();
    $view->assertSee('No project files yet')
        ->assertSee('@wheel="resizeWithWheel($event)"', false)
        ->assertSee('@contextmenu="openViewMenu($event)"', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($view->getContent());
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@data-library-view-surface and @tabindex="0"][.//p[normalize-space(.)="No project files yet"]]')->length)->toBe(1)
        ->and($xpath->query('//*[@role="menu" and @aria-label="View"]//button[@role="menuitemradio"]')->length)->toBe(4);
});

test('the project folder lists generated review papers with their source version and existing preview viewer', function () {
    [$version, $paper] = ($this->makeResponseVersion)();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    Storage::disk('local')->put('proposal-packages/screening.pdf', '%PDF-1.7 screening');
    Storage::disk('local')->put('proposal-packages/evaluation.pdf', '%PDF-1.7 evaluation');
    $screening = $version->files()->create([
        ...$paper->only(['file_path', 'original_filename', 'mime_type', 'file_size', 'checksum']),
        'file_path' => 'proposal-packages/screening.pdf',
        'document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'position' => 0,
    ]);
    $version->files()->create([
        ...$paper->only(['file_path', 'original_filename', 'mime_type', 'file_size', 'checksum']),
        'file_path' => 'proposal-packages/evaluation.pdf',
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'position' => 0, 'source_version_file_id' => $screening->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'co_evaluator_name' => 'Dr. Santos', 'narrative_evaluation' => 'Clarify the methodology.'],
    ]);
    $this->topic->update(['status' => 'revision_requested', 'review_stage' => 'gad']);
    $review = $this->topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'review_stage' => 'gad']);
    $review->fileRevisions()->create(['proposal_version_file_id' => $paper->id, 'document_type' => $paper->document_type, 'original_filename' => $paper->original_filename, 'revision_note' => 'Address the GAD checklist issues.']);

    foreach ([[$head, 'research_head'], [$this->faculty, 'faculty']] as [$viewer, $prefix]) {
        $response = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $prefix])->actingAs($viewer)
            ->get(route('topics.show', $this->topic))->assertOk();
        $response->assertViewHas('projectDocumentLibrary', function (array $library) use ($prefix, $review): bool {
            $papers = $library['documents']->where('generated_comment_response', true)->values();
            expect($papers)->toHaveCount(2)
                ->and($papers[0]['category'])->toBe(ProjectDocument::CATEGORY_REVIEWS_RESPONSES)
                ->and($papers->pluck('source')->join(' '))->toContain('Version 1', 'GAD Checklist', 'Co-Evaluator Review');
            foreach ($papers as $document) {
                expect($document['view_url'])->toContain('/comment-response-form/pdf', 'review='.$review->id)
                    ->and($document['view_url'])->toStartWith(route($prefix.'.topics.comment-response-form.pdf', $this->topic))
                    ->and($document['draft'])->toBeFalse();
            }

            return true;
        });
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new DOMXPath($dom);
        expect($xpath->query('//*[@data-project-document-drawer]//button[@data-project-document-preview-button]')->length)->toBe(5)
            ->and($xpath->query('//*[@data-project-document-drawer]//*[@data-project-document-review]')->length)->toBe(0)
            ->and($xpath->query('//*[@data-project-document-drawer]//*[@data-project-file-selection="proposal-version-file-'.$paper->id.'"]//button[@data-project-document-preview-button]')->length)->toBe(1)
            ->and($xpath->query('//*[@data-project-document-key and contains(@data-project-document-key, "comment-response-")]//a[@target="_blank"]')->length)->toBe(0)
            ->and($xpath->query('//*[@data-project-file-selection and contains(@data-project-file-selection, "comment-response-")]//a[@download]')->length)->toBe(2)
            ->and($xpath->query('//*[@data-project-document-preview-modal]//template[@x-if="show"]//*[@data-pdf-annotation-config]')->length)->toBe(5);
        $previewUrls = collect();
        foreach ($xpath->query('//*[@data-project-document-preview-modal]//*[@data-pdf-annotation-config]') as $viewerElement) {
            $configuration = json_decode($viewerElement->getAttribute('data-pdf-annotation-config'), true);
            expect($configuration['canAnnotate'])->toBeFalse();
            $previewUrls->push($configuration['pdfUrl']);
        }
        expect($previewUrls)->toContain(route('topics.versions.files.view', [$this->topic, $version, $paper]))
            ->and($previewUrls->filter(fn (string $url): bool => str_contains($url, 'review='.$review->id)))->toHaveCount(2);
    }
    expect($this->topic->projectDocuments()->count())->toBe(0)->and($version->files()->count())->toBe(3)->and($this->topic->reviews()->count())->toBe(1);
});

test('project folder keeps PDF viewing separate from Research Head annotation actions', function () {
    [$oldVersion, $oldPaper] = ($this->makeResponseVersion)();
    [$currentVersion, $currentPaper] = ($this->makeResponseVersion)(2);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $path = 'head-uploads/recorded-gad.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7 recorded assessment');
    $assessment = $currentVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 90, 'file_path' => $path, 'original_filename' => 'recorded-gad.pdf',
        'mime_type' => 'application/pdf',
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT],
    ]);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->actingAs($head);
    $documents = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $head)['documents'];
    expect($documents->firstWhere('key', 'proposal-version-file-'.$currentPaper->id)['view_url'])
        ->toBe(route('topics.versions.files.view', [$this->topic, $currentVersion, $currentPaper]))
        ->and($documents->firstWhere('key', 'proposal-version-file-'.$oldPaper->id))->not->toHaveKey('review_url')
        ->and($documents->firstWhere('key', 'proposal-version-file-'.$assessment->id))->not->toHaveKey('review_url');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])->actingAs($this->faculty);
    $facultyDocuments = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->faculty)['documents'];
    expect($facultyDocuments->firstWhere('key', 'proposal-version-file-'.$currentPaper->id))->not->toHaveKey('review_url');
});

test('submitted monitoring tools and progress reports appear once in the faculty project folder with preview and download', function (string $workspace, bool $collaborator) {
    $this->faculty->assignRole('faculty_researcher');
    $this->topic->update(['status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);
    $monitoringPath = 'progress-reports/'.$this->topic->id.'/monitoring.pdf';
    $progressPath = 'narrative-progress-reports/'.$this->topic->id.'/progress.pdf';
    Storage::disk('local')->put($monitoringPath, '%PDF-1.7 monitoring');
    Storage::disk('local')->put($progressPath, '%PDF-1.7 progress');
    $monitoring = $this->topic->progressReports()->create([
        'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-03-31',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'progress_percentage' => 30,
        'accomplishments' => 'Water sampling completed.', 'submitted_at' => now(),
        'official_pdf_path' => $monitoringPath, 'official_pdf_filename' => 'monitoring-tool.pdf',
    ]);
    $progress = $this->topic->narrativeReports()->create([
        'submitted_by' => $this->faculty->id, 'report_type' => 'progress', 'submission_date' => '2026-03-31',
        'researchers' => $this->faculty->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
        'budget' => 25000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Water sampling completed.',
        'introduction' => 'Water monitoring.', 'objectives' => 'Measure water quality.',
        'methodology' => 'Field sampling.', 'results_discussion' => 'Samples collected.', 'photos' => [],
        'submitted_at' => now(), 'official_pdf_path' => $progressPath, 'official_pdf_filename' => 'progress-report.pdf',
    ]);
    $viewer = $this->faculty;
    if ($collaborator) {
        $viewer = User::factory()->create();
        $viewer->assignRole($workspace);
        $this->topic->collaborators()->create([
            'user_id' => $viewer->id, 'name' => $viewer->name, 'email' => $viewer->email, 'accepted_at' => now(),
        ]);
    }
    $this->actingAs($viewer)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);

    $response = $this->get(route($workspace === 'faculty' ? 'topics.show' : 'research.show', $this->topic))->assertOk();
    $response->assertViewHas('projectDocumentLibrary', function (array $library) use ($monitoring, $progress): bool {
        expect($library['total'])->toBe(2)
            ->and($library['categories']->firstWhere('key', ProjectDocument::CATEGORY_MONITORING_REPORTS)['count'])->toBe(2)
            ->and($library['documents']->firstWhere('key', 'monitoring-report-'.$monitoring->id)['view_url'])->toBe(route('project-progress.monitoring-tool.view', $monitoring))
            ->and($library['documents']->firstWhere('key', 'narrative-report-'.$progress->id)['view_url'])->toBe(route('project-narrative-reports.view', $progress));

        return true;
    });
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-project-document-preview-modal]//*[@data-pdf-annotation-config]')->length)->toBe(2)
        ->and($this->topic->projectDocuments()->count())->toBe(0);

    foreach ([
        [route('project-progress.monitoring-tool.view', $monitoring), route('project-progress.monitoring-tool', $monitoring), 'monitoring-tool.pdf', '%PDF-1.7 monitoring'],
        [route('project-narrative-reports.view', $progress), route('project-narrative-reports.download', $progress), 'progress-report.pdf', '%PDF-1.7 progress'],
    ] as [$viewUrl, $downloadUrl, $filename, $contents]) {
        $preview = $this->get($viewUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($preview->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($preview->streamedContent())->toBe($contents);
        $this->get($downloadUrl)->assertDownload($filename);
    }
})->with([
    'Faculty owner' => ['faculty', false],
    'researcher owner' => ['faculty_researcher', false],
    'Faculty collaborator' => ['faculty', true],
]);

test('report folder access distinguishes the office secretary from proposal signing staff', function (string $role, string $workspace, bool $canReviewReports) {
    Role::findOrCreate($role, 'web');
    $viewer = User::factory()->create(['college' => 'College of Engineering']);
    $viewer->assignRole($role);
    $this->faculty->update(['college' => 'College of Engineering']);
    $this->topic->update([
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'research_secretary_id' => $viewer->id,
    ]);
    $path = 'progress-reports/private-monitoring.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7 private monitoring');
    $report = $this->topic->progressReports()->create([
        'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-03-31',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'progress_percentage' => 30,
        'accomplishments' => 'Water sampling completed.', 'submitted_at' => now(),
        'official_pdf_path' => $path, 'official_pdf_filename' => 'monitoring-tool.pdf',
    ]);
    $this->actingAs($viewer)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);

    $this->get(route('topics.show', $this->topic))->assertOk()
        ->assertViewHas('projectDocumentLibrary', fn (array $library): bool => $library['documents']->where('category', ProjectDocument::CATEGORY_MONITORING_REPORTS)->isNotEmpty() === $canReviewReports);
    $response = $this->get(route('project-progress.monitoring-tool.view', $report));
    $canReviewReports ? $response->assertOk() : $response->assertForbidden();
})->with([
    ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, false],
    ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, true],
]);

test('generated GAD and screening templates have their own folder category, apart from completed reviews', function () {
    [$version, $paper] = ($this->makeResponseVersion)();
    $templates = collect([
        ProposalVersionFile::TYPE_GAD_CHECKLIST => 'gad-template.pdf',
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM => 'screening-template.pdf',
    ])->map(function (string $filename, string $type) use ($version, $paper): ProposalVersionFile {
        $path = 'proposal-packages/'.$filename;
        Storage::disk('local')->put($path, '%PDF-1.7 generated template');

        return $version->files()->create([
            ...$paper->only(['mime_type', 'file_size', 'checksum']),
            'document_type' => $type,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $filename,
        ]);
    });
    $completedPath = 'proposal-packages/completed-gad.pdf';
    Storage::disk('local')->put($completedPath, '%PDF-1.7 completed GAD');
    $completed = $version->files()->create([
        ...$paper->only(['mime_type', 'file_size', 'checksum']),
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 0,
        'file_path' => $completedPath,
        'original_filename' => 'completed-gad.pdf',
        'source_version_file_id' => $templates[ProposalVersionFile::TYPE_GAD_CHECKLIST]->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT],
    ]);

    $library = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->faculty);
    $documents = $library['documents'];

    expect($documents->firstWhere('key', 'proposal-version-file-'.$paper->id)['category'])
        ->toBe(ProjectDocument::CATEGORY_PROPOSAL_PAPERS)
        ->and($documents->firstWhere('key', 'proposal-version-file-'.$completed->id)['category'])
        ->toBe(ProjectDocument::CATEGORY_REVIEWS_RESPONSES)
        ->and($library['categories']->firstWhere('key', ProjectDocument::CATEGORY_ASSESSMENT_FORMS)['count'])
        ->toBe(2)
        ->and($library['uploadCategories'])->not->toHaveKey(ProjectDocument::CATEGORY_ASSESSMENT_FORMS);

    foreach ($templates as $template) {
        expect($documents->firstWhere('key', 'proposal-version-file-'.$template->id)['category'])
            ->toBe(ProjectDocument::CATEGORY_ASSESSMENT_FORMS);
    }

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-project-file-category="assessment_forms"', false)
        ->assertSee('2 files');
});

test('legacy review comments remain accessible even when no proposal version was recorded', function () {
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create(['reviewer_id' => $this->faculty->id, 'decision' => 'revision_requested', 'comment' => 'Legacy review feedback.']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])->actingAs($this->faculty);
    $papers = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->faculty)['documents']->where('generated_comment_response', true);
    expect($papers)->toHaveCount(1)->and($papers->sole()['source'])->toBe('Version not recorded · Research Head Review')
        ->and($papers->sole()['view_url'])->toContain('review='.$review->id);
});

test('saved draft highlights appear only for the Research Head and only on the current version', function () {
    [$version, $paper] = ($this->makeResponseVersion)();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])->actingAs($head);
    expect(app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $head)['documents']->where('generated_comment_response', true))->toHaveCount(0);
    $paper->annotations()->create([
        'reviewer_id' => $head->id, 'annotation_type' => ProposalFileAnnotation::TYPE_AREA, 'page_number' => 1,
        'rectangles' => [['x' => .1, 'y' => .2, 'width' => .3, 'height' => .1]], 'comment' => 'Private draft feedback.',
    ]);
    $library = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $head);
    $draft = $library['documents']->where('generated_comment_response', true)->sole();
    expect($draft['draft'])->toBeTrue()->and($draft['source'])->toContain('Version 1', 'Research Head Review')
        ->and($draft['view_url'])->toContain('draft_version='.$version->id);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])->actingAs($this->faculty);
    expect(app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->faculty)['documents']->where('generated_comment_response', true))->toHaveCount(0);

    ($this->makeResponseVersion)(2);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])->actingAs($head);
    expect(app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $head)['documents']->where('generated_comment_response', true))->toHaveCount(0)
        ->and($paper->annotations()->count())->toBe(1)->and($this->topic->projectDocuments()->count())->toBe(0);
});

test('historical Comment Response entries retain their review and version after later reviews', function () {
    [$version, $paper] = ($this->makeResponseVersion)();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $review = $this->topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'review_stage' => 'initial', 'comment' => 'Original comments.']);
    $review->fileRevisions()->create(['proposal_version_file_id' => $paper->id, 'document_type' => $paper->document_type, 'original_filename' => $paper->original_filename]);
    [$laterVersion, $laterPaper] = ($this->makeResponseVersion)(2);
    $laterReview = $this->topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'review_stage' => 'lrec', 'committee_comments' => [['reviewer' => 'LREC member', 'comment' => 'Later committee comments.']]]);
    $laterReview->fileRevisions()->create(['proposal_version_file_id' => $laterPaper->id, 'document_type' => $laterPaper->document_type, 'original_filename' => $laterPaper->original_filename]);
    $this->topic->update(['status' => 'revision_requested', 'review_stage' => 'lrec']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])->actingAs($this->faculty);
    $papers = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->faculty)['documents']->where('generated_comment_response', true);
    expect($papers)->toHaveCount(2)
        ->and($papers->firstWhere('key', 'comment-response-review-'.$review->id.'-'.CommentResponseFeedback::FORM_RESEARCH_HEAD)['source'])->toBe('Version 1 · Research Head Review')
        ->and($papers->firstWhere('key', 'comment-response-review-'.$laterReview->id.'-'.CommentResponseFeedback::FORM_RESEARCH_HEAD)['source'])->toBe('Version 2 · LREC');
});

test('the faculty project leader can drag multiple PDFs into a categorized project folder', function () {
    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)->post(route('topics.documents.store', $this->topic), [
        'category' => ProjectDocument::CATEGORY_REVIEWS_RESPONSES,
        'note' => 'Corrected after the generated form used an outdated logo.',
        'documents' => [
            UploadedFile::fake()->createWithContent('corrected-checklist.pdf', "%PDF-1.7\ncorrected checklist"),
            UploadedFile::fake()->createWithContent('comment-response.pdf', "%PDF-1.7\ncomment response"),
        ],
    ]);

    $response
        ->assertRedirect(route('topics.show', $this->topic).'#project-files')
        ->assertSessionHas('project_documents_open', true);

    $documents = $this->topic->projectDocuments()->oldest()->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('category')->unique()->all())->toBe([ProjectDocument::CATEGORY_REVIEWS_RESPONSES])
        ->and($documents->pluck('uploaded_by')->unique()->all())->toBe([$this->faculty->id]);

    Storage::disk('local')->assertExists($documents->pluck('file_path')->all());

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('id="project-files-tab-button"', false)
        ->assertSee('data-project-document-browser', false)
        ->assertDontSee('data-project-documents-floating-trigger', false)
        ->assertSee('Reviews &amp; responses', false)
        ->assertSee('corrected-checklist.pdf')
        ->assertSee('comment-response.pdf')
        ->assertSee('Corrected after the generated form used an outdated logo.');
});

test('faculty researcher files use an inline folder browser with the shared poster and report records', function () {
    [$version, $paper] = ($this->makeResponseVersion)();
    $this->faculty->assignRole('faculty_researcher');
    $this->topic->update([
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
    ]);
    $posterPath = 'narrative-progress-reports/poster.png';
    Storage::disk('local')->put($posterPath, file_get_contents(public_path('images/sample-maintenance-poster.png')));
    Storage::disk('local')->put('narrative-progress-reports/terminal.pdf', "%PDF-1.7\nterminal report");
    $report = $this->topic->narrativeReports()->create([
        'submitted_by' => $this->faculty->id, 'report_type' => 'terminal', 'submission_date' => '2026-06-30',
        'submitted_at' => now(), 'researchers' => $this->faculty->name,
        'implementation_start' => '2025-06-01', 'implementation_end' => '2026-06-30',
        'budget' => 25000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Demonstration completed.',
        'introduction' => 'Demonstration research.', 'objectives' => 'Demonstrate project files.',
        'methodology' => 'Illustrative sample.', 'results_discussion' => 'Demonstration only.',
        'official_pdf_path' => 'narrative-progress-reports/terminal.pdf', 'official_pdf_filename' => 'terminal-report.pdf',
        'photos' => [['path' => $posterPath, 'original_name' => 'SAMPLE-poster.png', 'mime_type' => 'image/png', 'section' => 'cover', 'caption' => 'SAMPLE POSTER — not actual research findings.']],
    ]);
    $response = $this->actingAs($this->faculty)->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->get(route('research.show', $this->topic))->assertSuccessful()
        ->assertSee('id="project-files-tab-button"', false)
        ->assertSee('data-project-document-browser', false)
        ->assertDontSee('data-project-documents-floating-trigger', false)
        ->assertSee('Add PDF files')
        ->assertSee('@wheel="resizeWithWheel($event)"', false)
        ->assertSee('@contextmenu="openViewMenu($event)"', false)
        ->assertDontSee('aria-label="Item size"', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $files = '//*[@id="project-files-tab"]';
    $surfaces = $xpath->query($files.'//*[@data-library-view-surface]');
    expect($surfaces->length)->toBeGreaterThan(0);
    foreach ($surfaces as $surface) {
        expect($surface->getAttribute('tabindex'))->toBe('0');
    }
    expect($xpath->query($files.'//*[@data-project-file-category]')->length)->toBe(2)
        ->and($xpath->query($files.'//*[@data-workspace-sizing and @data-size="medium"]')->length)->toBe(1)
        ->and($xpath->query($files.'//button[not(@role="menuitemradio") and (normalize-space(.)="Icons" or normalize-space(.)="Details")]')->length)->toBe(0)
        ->and(trim($xpath->query('//*[@id="project-files-heading"]/following-sibling::p')->item(0)->textContent))->toBe('2 folders · 3 documents')
        ->and(trim($xpath->query('//*[@id="project-files-tab-button"]')->item(0)->textContent))->toBe('Folders 2')
        ->and($xpath->query($files.'//*[@data-project-document-key="proposal-version-file-'.$paper->id.'"]')->length)->toBe(1)
        ->and($xpath->query($files.'//*[@data-project-document-key="terminal-report-poster-'.$report->id.'-0"]//img')->length)->toBe(1)
        ->and($xpath->query($files.'//form[@id="project-file-upload"]')->length)->toBe(1)
        ->and($xpath->query($files.'//*[@data-pdf-annotation-config]')->length)->toBe(2);
    $menu = $xpath->query($files.'//*[@role="menu" and @aria-label="View"]')->item(0);
    expect($menu)->not->toBeNull();
    expect($menu->hasAttribute('x-cloak'))->toBeTrue()
        ->and($menu->hasAttribute('x-show'))->toBeTrue()
        ->and($xpath->query('.//button[@role="menuitemradio"]', $menu)->length)->toBe(4);
    foreach (['small' => 'Small icons', 'medium' => 'Medium icons', 'large' => 'Large icons', 'list' => 'Details'] as $option => $label) {
        $button = $xpath->query('.//button[@type="button" and @role="menuitemradio" and @data-view-option="'.$option.'"]', $menu)->item(0);
        expect($button)->not->toBeNull();
        expect(trim($button->textContent))->toBe($label)
            ->and($button->hasAttribute('aria-checked') || $button->hasAttribute(':aria-checked') || $button->hasAttribute('x-bind:aria-checked'))->toBeTrue();
    }
    if (getenv('ATHENA_EXPORT_PROJECT_FILES') === '1') {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/project-files-faculty.html', $response->getContent());
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $reader = $this->actingAs($head)->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->get(route('topics.show', $this->topic))->assertSuccessful()->assertDontSee('Add PDF files');
    $reader->assertSee('2 folders · 3 documents')->assertSee('Browse 2 folders');
    if (getenv('ATHENA_EXPORT_PROJECT_FILES') === '1') {
        file_put_contents(storage_path('framework/testing/project-files-reader.html'), $reader->getContent());
    }
});

test('project PDF upload feedback reopens the inline Files tab', function (bool $hasErrors) {
    $session = [
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
        'project_documents_open' => ! $hasErrors,
    ];
    $this->actingAs($this->faculty)->withSession($session);
    if ($hasErrors) {
        $this->from(route('topics.show', $this->topic))
            ->post(route('topics.documents.store', $this->topic), [
                'category' => ProjectDocument::CATEGORY_SUPPORTING_FILES,
                'documents' => [UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            ])->assertSessionHasErrors(['documents.0'], null, 'projectDocuments');
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
    }
    $response = $this->get(route('topics.show', $this->topic))->assertSuccessful();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-topic-workspace]')->item(0)->getAttribute('x-data'))->toContain("activeTopicTab: 'files'");
    if ($hasErrors) {
        $response->assertSee('Every project file must be a PDF.');
    }
})->with(['successful upload' => false, 'invalid upload' => true]);

test('project document uploads accept only PDFs and no more than ten files', function () {
    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->from(route('topics.show', $this->topic))
        ->post(route('topics.documents.store', $this->topic), [
            'category' => ProjectDocument::CATEGORY_SUPPORTING_FILES,
            'documents' => [UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ]);

    $response->assertSessionHasErrors(['documents.0'], null, 'projectDocuments');
    expect($this->topic->projectDocuments()->count())->toBe(0);
});

test('another faculty member cannot add files to a project they do not own', function () {
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($otherFaculty)
        ->post(route('topics.documents.store', $this->topic), [
            'category' => ProjectDocument::CATEGORY_OTHER,
            'documents' => [UploadedFile::fake()->createWithContent('private.pdf', "%PDF-1.7\nprivate")],
        ])
        ->assertForbidden();

    expect($this->topic->projectDocuments()->count())->toBe(0);
});

test('the folder aggregates proposal papers and the released Notice to Proceed', function () {
    Storage::disk('local')->put('proposal-packages/detailed.pdf', '%PDF-1.7 proposal');
    Storage::disk('local')->put('notices-to-proceed/signed.pdf', '%PDF-1.7 notice');

    $version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposal-packages/detailed.pdf',
        'original_filename' => 'detailed-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', '%PDF-1.7 proposal'),
        'title' => $this->topic->title,
        'estimated_budget' => $this->topic->estimated_budget,
        'estimated_duration_months' => $this->topic->estimated_duration_months,
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposal-packages/detailed.pdf',
        'original_filename' => 'detailed-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', '%PDF-1.7 proposal'),
    ]);
    $this->topic->update([
        'status' => 'approved',
        'project_status' => TopicProposal::PROJECT_STATUS_ONGOING,
        'notice_to_proceed_path' => 'notices-to-proceed/signed.pdf',
        'notice_to_proceed_original_filename' => 'notice-to-proceed.pdf',
        'notice_to_proceed_issued_at' => now(),
    ]);
    $this->faculty->assignRole('faculty_researcher');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Proposal papers')
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Notice to Proceed')
        ->assertSee('notice-to-proceed.pdf');
});

test('project document routes are scoped to their project', function () {
    $otherTopic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Another owned project',
        'estimated_budget' => 10_000,
        'estimated_duration_months' => 4,
        'status' => 'pending',
    ]);
    Storage::disk('local')->put('project-documents/'.$this->topic->id.'/record.pdf', '%PDF-1.7 record');
    $document = $this->topic->projectDocuments()->create([
        'uploaded_by' => $this->faculty->id,
        'category' => ProjectDocument::CATEGORY_OTHER,
        'title' => 'Project record',
        'file_path' => 'project-documents/'.$this->topic->id.'/record.pdf',
        'original_filename' => 'record.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 15,
        'checksum' => hash('sha256', '%PDF-1.7 record'),
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.documents.download', [$otherTopic, $document]))
        ->assertNotFound();
});
