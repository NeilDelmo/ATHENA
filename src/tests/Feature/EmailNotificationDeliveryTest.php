<?php

use App\Actions\ProcessResearchCallOpeningNotifications;
use App\Actions\SendProposalWorkspaceInvitation;
use App\Actions\SyncTopicCollaborators;
use App\Models\ProposalDraft;
use App\Models\ProposalInvitationEmail;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\WorkspaceActionItems;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $faculty = Role::findOrCreate('faculty');
    $this->head = User::factory()->create()->assignRole(Role::findOrCreate('research_head'));
    $this->owner = User::factory()->create()->assignRole($faculty);
    $this->invitee = User::factory()->create(['email' => 'invited@g.batstate-u.edu.ph'])->assignRole($faculty);
    $this->call = ResearchCall::create([
        'title' => 'Coastal Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(5),
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $this->call->id,
        'project_title' => 'Coastal Research Proposal',
    ]);
    $this->member = $this->draft->members()->create([
        'name' => $this->invitee->name,
        'email' => $this->invitee->email,
        'accepted_at' => null,
    ]);
    $this->withoutVite();
});

test('invitation status reflects queueing, failure and a successful resend', function () {
    Queue::fake([SendQueuedNotifications::class]);

    $this->actingAs($this->owner)->post(route('faculty.proposal-drafts.members.invitation', [$this->draft, $this->member]))
        ->assertSessionHas('success', 'Invitation email queued for '.$this->member->name.'.');
    $workspace = route('faculty.proposal-drafts.show', $this->draft);
    expect($this->get($workspace)->assertOk()->getContent())->toMatch('/data-invitation-email-status[^>]*>Invitation email queued\./');

    $job = Queue::pushed(SendQueuedNotifications::class)->sole();
    Event::listen(MessageSending::class, fn () => throw new RuntimeException('Provider rejected test email'));
    expect(fn () => $job->handle(app(ChannelManager::class)))->toThrow(RuntimeException::class, 'Provider rejected test email');
    $job->failed(new RuntimeException('Provider rejected test email'));
    $this->get($workspace)->assertSee('Invitation email failed. You can resend it.');

    Event::forget(MessageSending::class);
    $this->post(route('faculty.proposal-drafts.members.invitation', [$this->draft, $this->member]))
        ->assertSessionHas('success');
    $job->failed(new RuntimeException('Earlier attempt failed'));
    Queue::pushed(SendQueuedNotifications::class)->last()->handle(app(ChannelManager::class));
    $job->handle(app(ChannelManager::class));

    $this->get($workspace)->assertSee('Invitation email captured by the test mailer');
    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(1);
});

test('pending invitations can be sent and answered after the draft is submitted', function (bool $accept) {
    Queue::fake([SendQueuedNotifications::class]);
    app(SendProposalWorkspaceInvitation::class)->handle($this->draft, $this->member);
    $url = route('notifications.proposal-invitations.show', $this->member);
    $responseUrl = route($accept ? 'notifications.proposal-invitations.accept' : 'notifications.proposal-invitations.decline', $this->member);
    $topic = TopicProposal::create([
        'user_id' => $this->owner->id,
        'research_call_id' => $this->call->id,
        'title' => $this->draft->project_title,
        'status' => 'pending',
    ]);
    DB::transaction(function () use ($topic): void {
        app(SyncTopicCollaborators::class)->handle($this->draft, $topic);
        $this->draft->forceDelete();
    });

    expect(ProposalInvitationEmail::find($this->member->id)?->topic_collaborator_id)
        ->toBe($topic->collaborators()->sole()->id);
    $jobs = Queue::pushed(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(1);
    $jobs->sole()->handle(app(ChannelManager::class));
    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(1);
    $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe($this->invitee->email)
        ->and($message->getHtmlBody())->toContain($url);

    $topicUrl = route('topics.show', $topic);
    $this->actingAs($this->owner)->get($topicUrl)->assertOk()->assertSee('Invitation email captured by the test mailer');
    $resendUrl = route('faculty.topic-collaborators.invitation', [$topic, $topic->collaborators()->sole()]);
    $this->post($resendUrl)->assertRedirect($topicUrl)->assertSessionHas('success');
    Queue::pushed(SendQueuedNotifications::class)->last()->handle(app(ChannelManager::class));
    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(2);
    $this->get($url)->assertForbidden();
    $this->post($responseUrl)->assertForbidden();
    $this->actingAs($this->invitee)->get($url)->assertOk()->assertSee('Accept invitation');
    $this->post($resendUrl)->assertForbidden();
    $actions = app(WorkspaceActionItems::class);
    expect(collect($actions->for($this->invitee))->pluck('url'))->toContain($url);

    $this->post($responseUrl)->assertRedirect($accept ? $topicUrl : route('faculty.dashboard'));
    expect($topic->collaborators()->forUser($this->invitee)->exists())->toBe($accept)
        ->and($topic->collaborators()->count())->toBe($accept ? 1 : 0)
        ->and(collect($actions->for($this->invitee))->pluck('url'))->not->toContain($url);
})->with([true, false]);

test('a research call opening reminder emails the research head', function () {
    config(['queue.default' => 'database']);
    $this->call->update(['opens_at' => now()->addHours(12)]);

    expect(app(ProcessResearchCallOpeningNotifications::class)->process($this->call))->toBeTrue();
    expect($this->head->notifications()->count())->toBe(1)
        ->and($this->invitee->notifications()->count())->toBe(0);

    $queue = Queue::connection('database');
    while ($job = $queue->pop()) {
        $job->fire();
    }

    $mail = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($mail->getSubject())->toBe('Research call opens tomorrow')
        ->and($mail->getTo()[0]->getAddress())->toBe($this->head->email);
});

test('research call announcements and deadline reminders queue emails for faculty', function () {
    config(['queue.default' => 'database']);
    $action = app(ProcessResearchCallOpeningNotifications::class);

    expect($action->process($this->call))->toBeTrue();
    expect($action->processDeadlineReminder($this->call))->toBe(2);
    expect($action->processDeadlineReminder($this->call))->toBe(0);
    expect($this->invitee->notifications()->count())->toBe(2)
        ->and($this->head->notifications()->count())->toBe(0);

    $queue = Queue::connection('database');
    $mailJobs = DB::table('jobs')->pluck('payload')->filter(function (string $payload): bool {
        return json_decode($payload, true)['data']['commandName'] === SendQueuedNotifications::class;
    });
    expect($mailJobs)->toHaveCount(4);
    while ($job = $queue->pop()) {
        $job->fire();
    }

    $messages = Mail::mailer()->getSymfonyTransport()->messages()->map->getOriginalMessage();
    expect($messages)->toHaveCount(4)
        ->and($messages->map->getSubject()->unique()->values()->all())->toBe([
            'New research call available',
            'Research call deadline approaching',
        ])
        ->and($messages->flatMap->getTo()->map->getAddress()->unique()->sort()->values()->all())
        ->toBe(collect([$this->invitee->email, $this->owner->email])->sort()->values()->all());
});
