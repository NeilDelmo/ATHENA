<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\TerminalReportUiDemoSeeder;
use Illuminate\Support\Facades\Storage;

test('terminal demo enriches only the existing sample and preserves review and signature records', function () {
    Storage::fake('local');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->times(4)->andReturn("%PDF-1.4\nTerminal demo\n%%EOF");
    $owner = User::factory()->create();
    $topic = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Clinic records demo', 'status' => 'approved', 'description' => '[lifecycle-demo:ongoing-second] Existing sample']);
    $realTopic = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Real project', 'status' => 'approved']);
    $data = [
        'submitted_by' => $owner->id, 'report_type' => 'terminal', 'submission_date' => now(),
        'researchers' => $owner->name, 'implementation_start' => now()->subMonths(9), 'implementation_end' => now(),
        'budget' => 24000, 'funding_agency' => 'University grant', 'objectives' => 'Develop and evaluate a prototype.',
        'introduction' => 'Original introduction.', 'methodology' => 'Original methods.', 'results_discussion' => 'Original findings.',
        'accomplishment_summary' => 'UI DEMONSTRATION DATA: Existing sample.', 'photos' => [],
        'terminal_data' => ['abstract' => 'Existing abstract.', 'signed_copy' => ['path' => 'existing-signed.pdf']],
        'review_status' => 'pending', 'submission_status' => 'submitted', 'research_head_remarks' => 'Preserve this feedback.',
    ];
    $report = ProjectNarrativeReport::create([...$data, 'topic_id' => $topic->id, 'tracking_number' => 'LIFE-'.$topic->id.'-TERMINAL']);
    $progress = ProjectNarrativeReport::create([
        ...$data, 'topic_id' => $topic->id, 'report_type' => 'progress',
        'tracking_number' => 'LIFE-'.$topic->id.'-NARRATIVE',
        'submission_date' => now()->subMonths(3), 'terminal_data' => null,
        'review_status' => 'reviewed',
    ]);
    $untouched = ProjectNarrativeReport::create([...$data, 'topic_id' => $realTopic->id, 'tracking_number' => 'REAL-TERMINAL']);
    $this->seed(TerminalReportUiDemoSeeder::class);
    $this->seed(TerminalReportUiDemoSeeder::class);
    $report->refresh();
    expect(ProjectNarrativeReport::count())->toBe(3)
        ->and($progress->fresh()->submission_date->lt($report->submission_date))->toBeTrue()
        ->and($progress->fresh()->review_status)->toBe('reviewed')
        ->and(Storage::disk('local')->get($progress->fresh()->official_pdf_path))->toStartWith('%PDF')
        ->and($report->photos)->toHaveCount(7)
        ->and($report->review_status)->toBe('pending')
        ->and($report->research_head_remarks)->toBe('Preserve this feedback.')
        ->and($report->signedCopy()['path'])->toBe('existing-signed.pdf')
        ->and($report->terminal_data['abstract'])->toBe('Existing abstract.')
        ->and($untouched->fresh()->introduction)->toBe('Original introduction.')
        ->and(Storage::disk('local')->get($report->official_pdf_path))->toStartWith('%PDF');
    $backup = json_decode(Storage::disk('local')->get('post-approval-ui-demo/terminal-reader-before-refresh-'.$report->id.'.json'), true);
    expect($backup['introduction'])->toBe('Original introduction.');
    foreach ($report->photos as $photo) {
        Storage::disk('local')->assertExists($photo['path']);
    }
});
