<?php

use App\Actions\NotifyRmsQuarterlyReportStatus;
use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\RmsQuarterlyReportStatus;
use App\Services\SidebarAttentionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo('2026-10-15 09:00:00');
    config(['broadcasting.default' => 'null']);
    Storage::fake('local');
    foreach (['research_head', 'research_coordinator', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole(['research_head', 'faculty']);
    $this->otherHead = User::factory()->create();
    $this->otherHead->assignRole('research_head');
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CHS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->reportInput = fn (): array => ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'submit', 'form_complete' => '1', 'draft_token' => (CollegeQuarterlyReport::where('college', $this->coordinator->college)->where('reporting_year', 2026)->where('reporting_quarter', 3)->first() ?? new CollegeQuarterlyReport)->draftToken()];
    Event::fake([BroadcastNotificationCreated::class]);
});

function submittedRmsNotificationCollege(string $college): CollegeQuarterlyReport
{
    $report = CollegeQuarterlyReport::factory()->create([
        'college' => $college, 'reporting_year' => 2026, 'reporting_quarter' => 3, 'submitted_at' => now(),
    ]);
    CollegeQuarterlyReportSubmission::factory()->for($report, 'report')->create();

    return $report;
}

test('RMS notices require current submissions from all six colleges and do not create report drafts', function () {
    foreach (array_slice(array_values(User::COLLEGES), 0, 5) as $college) {
        submittedRmsNotificationCollege($college);
    }
    $last = CollegeQuarterlyReport::factory()->create([
        'college' => User::COLLEGES['CHS'], 'reporting_year' => 2026, 'reporting_quarter' => 3,
    ]);
    $action = app(NotifyRmsQuarterlyReportStatus::class);
    $action->handle(2026, 3);
    expect($this->head->notifications()->count())->toBe(0);

    $submission = CollegeQuarterlyReportSubmission::factory()->for($last, 'report')->create(['returned_at' => now()]);
    $last->update(['submitted_at' => now()]);
    $action->handle(2026, 3);
    expect($this->head->notifications()->count())->toBe(0);

    CollegeQuarterlyReportSubmission::factory()->for($last, 'report')->create(['version_number' => 2]);
    $action->handle(2026, 3);
    $action->handle(2026, 3);
    foreach ([$this->head, $this->otherHead] as $head) {
        $notice = $head->notifications()->sole();
        expect($notice->data['title'])->toBe('All college reports received')
            ->and($notice->data['level'])->toBe('success')
            ->and($notice->data['workspace'])->toBe(User::WORKSPACE_RESEARCH_HEAD)
            ->and($notice->data['sidebar_area'])->toBe(ProposalActivityNotification::SIDEBAR_AREA_RMS_REPORTS)
            ->and($notice->data['url'])->toBe(route('research_head.rms-quarterly-reports.index', ['year' => 2026, 'quarter' => 3]))
            ->and($notice->data['action_data']['rms_period_key'])->toBe('2026-Q3')
            ->and($notice->data['action_data']['rms_status_token'])->not->toBeEmpty();
    }
    expect($this->coordinator->notifications()->count())->toBe(0)
        ->and(RmsQuarterlyReport::count())->toBe(0)
        ->and($submission->fresh()->returned_at)->not->toBeNull();
    Event::assertDispatchedTimes(BroadcastNotificationCreated::class, 2);
});

test('saving a coordinator draft stays quiet and its final submission sends the RMS ready notice', function () {
    foreach (array_slice(array_values(User::COLLEGES), 0, 5) as $college) {
        submittedRmsNotificationCollege($college);
    }
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->post(route('research_coordinator.reports.store'), [...($this->reportInput)(), 'intent' => 'save'])
        ->assertSessionHasNoErrors();
    expect($this->head->notifications()->count())->toBe(0);

    $this->post(route('research_coordinator.reports.store'), ($this->reportInput)())->assertSessionHasNoErrors();
    expect($this->head->notifications()->sole()->data['title'])->toBe('All college reports received')
        ->and($this->otherHead->notifications()->count())->toBe(1)
        ->and(RmsQuarterlyReport::count())->toBe(0);
    $this->postJson(route('research_coordinator.reports.store'), ($this->reportInput)())
        ->assertUnprocessable()->assertJsonValidationErrors('intent');
    expect($this->head->notifications()->count())->toBe(1);
});

test('returning an exported college report warns the head and corrected resubmission restores ready status in one inbox group', function () {
    foreach (array_values(User::COLLEGES) as $college) {
        submittedRmsNotificationCollege($college);
    }
    $rms = RmsQuarterlyReport::factory()->create(['reporting_year' => 2026, 'reporting_quarter' => 3]);
    $status = app(RmsQuarterlyReportStatus::class);
    $rms->update(['downloaded_at' => now(), 'downloaded_fingerprint' => $status->fingerprint($status->forPeriod(2026, 3, $rms), $rms)]);
    $report = CollegeQuarterlyReport::where('college', $this->coordinator->college)->sole();
    $submission = $report->latestSubmission;
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->patch(route('research_head.quarterly-reports.return', $report), [
        'submission_id' => $submission->id, 'reason' => 'Include the corrected college activity.',
    ])->assertSessionHasNoErrors();

    $returnedNotice = $this->head->notifications()->sole();
    expect($returnedNotice->data['title'])->toBe('RMS report awaiting corrected submissions')
        ->and($returnedNotice->data['level'])->toBe('warning')
        ->and($status->forPeriod(2026, 3, $rms)['ready'])->toBeFalse();
    $attention = app(SidebarAttentionService::class);
    expect($attention->countsFor($this->head)['rms_reports'])->toBe(1)
        ->and($attention->canOpen($this->head, 'rms_reports'))->toBeTrue()
        ->and($attention->routeNameFor('rms_reports'))->toBe('research_head.rms-quarterly-reports.index')
        ->and($attention->requiresCompletedReview('rms_reports'))->toBeFalse();
    app(NotifyRmsQuarterlyReportStatus::class)->handle(2026, 3);
    expect($this->head->notifications()->count())->toBe(1);

    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->post(route('research_coordinator.reports.store'), [...($this->reportInput)(), 'intent' => 'save'])
        ->assertSessionHasNoErrors();
    expect($this->head->notifications()->count())->toBe(1);
    $this->travel(1)->minute();
    $this->post(route('research_coordinator.reports.store'), ($this->reportInput)())->assertSessionHasNoErrors();
    $updatedNotice = $this->head->notifications()->latest()->firstOrFail();
    expect($updatedNotice->data['title'])->toBe('RMS report updated since download')
        ->and($updatedNotice->data['level'])->toBe('warning')
        ->and($updatedNotice->data['action_data']['rms_status_token'])->not->toBe($returnedNotice->data['action_data']['rms_status_token'])
        ->and($status->forPeriod(2026, 3, $rms)['ready'])->toBeTrue()
        ->and($status->forPeriod(2026, 3, $rms)['outdated'])->toBeTrue()
        ->and($this->head->notifications()->count())->toBe(2)
        ->and($this->otherHead->notifications()->count())->toBe(2);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.group_key', 'rms:2026-Q3')
        ->assertJsonPath('notifications.0.update_count', 2)
        ->assertJsonPath('notifications.0.unread_count', 2)
        ->assertJsonPath('notifications.0.data.title', 'RMS report updated since download');
    expect($attention->countsFor($this->head)['rms_reports'])->toBe(2);
    foreach ([[], ['year' => 2026, 'quarter' => 3]] as $location) {
        $this->get(route('research_head.rms-quarterly-reports.index', $location))->assertOk()
            ->assertSee('data-sidebar-attention-url="'.route('sidebar-attention.open', 'rms_reports').'"', false);
    }
    $this->post(route('sidebar-attention.open', 'rms_reports'))
        ->assertRedirect(route('research_head.rms-quarterly-reports.index'));
    expect($attention->countsFor($this->head)['rms_reports'])->toBe(0)
        ->and($this->head->notifications()->whereNull('read_at')->count())->toBe(0)
        ->and($this->otherHead->notifications()->whereNull('read_at')->count())->toBe(2);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect($attention->canOpen($this->head, 'rms_reports'))->toBeFalse();
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(0, 'notifications');
});

test('RMS status notifications wait for a successful commit and are discarded on rollback', function () {
    foreach (array_values(User::COLLEGES) as $college) {
        submittedRmsNotificationCollege($college);
    }
    try {
        DB::transaction(function (): void {
            app(NotifyRmsQuarterlyReportStatus::class)->handle(2026, 3);
            expect($this->head->notifications()->count())->toBe(0);
            throw new RuntimeException('Cancel the source change.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Cancel the source change.');
    }
    expect($this->head->notifications()->count())->toBe(0);

    DB::transaction(function (): void {
        app(NotifyRmsQuarterlyReportStatus::class)->handle(2026, 3);
        expect($this->head->notifications()->count())->toBe(0);
    });
    expect($this->head->notifications()->count())->toBe(1);
});

test('a notification failure cannot delete evidence already saved with a submitted report', function () {
    mock(NotifyRmsQuarterlyReportStatus::class)->shouldReceive('handle')->once()
        ->with(2026, 3)->andThrow(new RuntimeException('Notification delivery failed.'));
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->post(route('research_coordinator.reports.store'), [...($this->reportInput)(),
        'evidence_images' => [UploadedFile::fake()->image('submitted-evidence.png')],
    ])->assertServerError();

    $report = CollegeQuarterlyReport::sole();
    expect($report->submitted_at)->not->toBeNull()
        ->and($report->submissions()->count())->toBe(1);
    Storage::disk('local')->assertExists($report->evidence[0]['path']);
});

test('a recipient delivery failure rolls back every RMS database notice for that status', function () {
    foreach (array_values(User::COLLEGES) as $college) {
        submittedRmsNotificationCollege($college);
    }
    Event::listen(NotificationSending::class, function (NotificationSending $event): void {
        if ($event->channel === 'database' && $event->notifiable->is($this->otherHead)) {
            throw new RuntimeException('Second recipient delivery failed.');
        }
    });

    expect(fn () => app(NotifyRmsQuarterlyReportStatus::class)->handle(2026, 3))
        ->toThrow(RuntimeException::class, 'Second recipient delivery failed.');
    expect($this->head->notifications()->count())->toBe(0)
        ->and($this->otherHead->notifications()->count())->toBe(0);
});
