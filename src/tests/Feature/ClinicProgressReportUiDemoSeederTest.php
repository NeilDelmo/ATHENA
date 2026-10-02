<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\ClinicProgressReportUiDemoSeeder;
use Illuminate\Support\Facades\Storage;

test('clinic demo adds three distinct interim reports without altering existing submissions or duplicating samples', function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->times(3)->andReturn("%PDF-1.4\nSample progress\n%%EOF");
    $owner = User::factory()->create();
    $head = User::factory()->create();
    $topic = TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Offline-First Clinic Records System for Barangay Health Stations',
        'description' => '[lifecycle-demo:ongoing-second] Existing sample', 'status' => 'approved',
        'notice_to_proceed_issued_at' => '2025-10-01', 'estimated_duration_months' => 9,
        'notice_to_proceed_data' => ['approved_start_date' => '2025-10-01', 'approved_end_date' => '2026-06-30'],
    ]);
    $data = [
        'topic_id' => $topic->id, 'submitted_by' => $owner->id, 'report_type' => 'progress',
        'submission_date' => '2026-04-01', 'researchers' => $owner->name,
        'implementation_start' => '2025-10-01', 'implementation_end' => '2026-06-30',
        'budget' => 24000, 'funding_agency' => 'University grant', 'objectives' => 'Validate the prototype.',
        'introduction' => 'Preserve existing report content.', 'methodology' => 'Existing method.',
        'results_discussion' => 'Existing results.', 'accomplishment_summary' => 'UI DEMONSTRATION DATA: Existing sample.',
        'photos' => [], 'review_status' => 'reviewed', 'reviewed_by' => $head->id,
        'research_head_remarks' => 'Preserve existing feedback.', 'submission_status' => 'submitted',
    ];
    $existing = ProjectNarrativeReport::create([...$data, 'tracking_number' => 'LIFE-'.$topic->id.'-NARRATIVE']);
    $terminal = ProjectNarrativeReport::create([
        ...$data, 'report_type' => 'terminal', 'tracking_number' => 'LIFE-'.$topic->id.'-TERMINAL',
        'submission_date' => '2026-07-01', 'review_status' => 'pending',
    ]);
    $other = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Real project', 'status' => 'approved']);
    ProjectNarrativeReport::create([...$data, 'topic_id' => $other->id, 'tracking_number' => 'REAL-PROGRESS']);
    $terminalBefore = $terminal->fresh()->getAttributes();

    $this->seed(ClinicProgressReportUiDemoSeeder::class);
    $samples = $topic->narrativeReports()->where('tracking_number', 'like', '%-PROGRESS-%')->get();
    expect($samples)->toHaveCount(3)
        ->and($topic->narrativeReports()->where('report_type', 'progress')->count())->toBe(4)
        ->and($other->narrativeReports()->count())->toBe(1)
        ->and($samples->pluck('introduction')->unique())->toHaveCount(3)
        ->and($samples->pluck('official_pdf_path')->unique())->toHaveCount(3)
        ->and($existing->fresh()->introduction)->toBe('Preserve existing report content.')
        ->and($terminal->fresh()->getAttributes())->toBe($terminalBefore);
    foreach ($samples as $sample) {
        expect($sample->submission_date->lt($terminal->submission_date))->toBeTrue()
            ->and($sample->report_type)->toBe('progress')
            ->and($sample->review_status)->toBe('reviewed')
            ->and($sample->introduction)->toStartWith('UI DEMONSTRATION ONLY.')
            ->and(strlen($sample->results_discussion))->toBeGreaterThan(450)
            ->and($sample->photos)->not->toBeEmpty()
            ->and($sample->accomplishments)->toHaveCount(1)
            ->and(Storage::disk('local')->get($sample->official_pdf_path))->toStartWith('%PDF');
        foreach ($sample->photos as $photo) {
            Storage::disk('local')->assertExists($photo['path']);
        }
    }
    $sample = $samples->first();
    $sample->update(['research_head_remarks' => 'Keep my updated review.']);
    $this->seed(ClinicProgressReportUiDemoSeeder::class);
    expect($topic->narrativeReports()->count())->toBe(5)
        ->and($sample->fresh()->research_head_remarks)->toBe('Keep my updated review.');
});

test('clinic demo leaves ordinary projects alone when the marked source report is absent', function () {
    $this->mock(DocumentPdfConverter::class)->shouldNotReceive('convertDocx');
    $this->seed(ClinicProgressReportUiDemoSeeder::class);
    expect(ProjectNarrativeReport::count())->toBe(0);
});
