<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchDashboardAnalytics;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->travelTo(now()->setDate(2026, 9, 6)->setTime(10, 0));
    $this->call = ResearchCall::create(['title' => 'Analytics call', 'academic_year' => '2026', 'opens_at' => now()->subMonth(), 'closes_at' => now()->addDay(), 'status' => 'open']);
    $this->makeTopic = fn (array $attributes = []) => TopicProposal::create(array_merge(['user_id' => $this->faculty->id, 'research_call_id' => $this->call->id, 'title' => 'Research proposal', 'status' => 'pending'], $attributes));
});

test('stage transitions record elapsed time and ignore unrelated edits', function () {
    $topic = ($this->makeTopic)();
    $start = $topic->status_started_at->copy();
    $this->travel(3)->days();
    $topic->update(['title' => 'Renamed']);
    expect($topic->status_started_at->equalTo($start))->toBeTrue()->and($topic->stageTransitions()->count())->toBe(1);
    $topic->update(['status' => 'revision_requested']);
    $transition = $topic->stageTransitions()->latest('id')->first();
    expect($transition->from_status)->toBe('pending')->and($transition->to_status)->toBe('revision_requested')
        ->and((int) $transition->previous_started_at->diffInDays($transition->changed_at))->toBe(3);
    $this->travel(2)->days();
    $topic->update(['status' => 'resubmitted']);
    $data = app(ResearchDashboardAnalytics::class)->summarize($this->call->id);
    expect((float) $data['stageDurations']->firstWhere('from_status', 'pending')->average_days)->toBe(3.0)
        ->and((float) $data['stageDurations']->firstWhere('from_status', 'revision_requested')->average_days)->toBe(2.0);
});

test('historical unknown stages are excluded and future tracking starts without a fabricated backfill', function () {
    $topic = ($this->makeTopic)();
    TopicProposal::whereKey($topic->id)->update(['status_started_at' => null]);
    $topic->stageTransitions()->delete();
    $topic->refresh();
    $data = app(ResearchDashboardAnalytics::class)->summarize($this->call->id);
    expect($data['unknownTiming'])->toBe(1)->and($data['attention']->first()['days'])->toBeNull();
    $topic->update(['status' => 'expert_review']);
    expect($topic->stageTransitions()->sole()->previous_started_at)->toBeNull();
    expect(app(ResearchDashboardAnalytics::class)->summarize($this->call->id)['stageDurations'])->toBeEmpty();
});

test('weekly submission totals include resubmissions and zero weeks with a research call filter', function () {
    $topic = ($this->makeTopic)();
    foreach ([now()->subWeeks(5), now()->subWeek(), now()] as $index => $date) {
        $version = $topic->versions()->make(['file_path' => 'test.pdf', 'original_filename' => 'test.pdf', 'title' => 'Test', 'estimated_budget' => 1000, 'estimated_duration_months' => 6, 'submitted_by' => $this->faculty->id, 'version_number' => $index + 1, 'submission_type' => $index === 0 ? 'initial' : 'revision']);
        $version->created_at = $date;
        $version->save();
    }
    $otherCall = $this->call->replicate();
    $otherCall->title = 'Other call';
    $otherCall->save();
    $other = ($this->makeTopic)(['research_call_id' => $otherCall->id]);
    $other->versions()->create(['file_path' => 'test.pdf', 'original_filename' => 'test.pdf', 'title' => 'Test', 'estimated_budget' => 1000, 'estimated_duration_months' => 6, 'submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial']);
    $data = app(ResearchDashboardAnalytics::class)->summarize($this->call->id);
    expect($data['weeks'])->toHaveCount(8)->and($data['weeks']->sum('count'))->toBe(3)
        ->and($data['recentTotal'])->toBe(2)->and($data['previousTotal'])->toBe(1)
        ->and($data['weeks']->where('count', 0)->count())->toBe(5);
});

test('attention uses actual dates and flags only an applicable expired revision deadline', function () {
    $this->call->update(['paper_revisions_end_date' => now()->subDay()]);
    $old = ($this->makeTopic)(['title' => 'Old revision', 'status' => 'revision_requested']);
    $this->travel(3)->days();
    $new = ($this->makeTopic)(['title' => 'New review']);
    foreach (range(1, 2) as $round) {
        $old->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Please revise']);
    }
    $data = app(ResearchDashboardAnalytics::class)->summarize($this->call->id);
    expect($data['attention']->first()['topic']->id)->toBe($old->id)
        ->and($data['attention']->first()['days'])->toBe(3)
        ->and($data['attention']->first()['past_revision_deadline'])->toBeTrue()
        ->and($data['attention']->last()['past_revision_deadline'])->toBeFalse()
        ->and($data['repeatRevisions'])->toBe(1);
    $this->actingAs($this->head);
    Livewire::test(ResearchHeadDashboard::class)->set('status', 'rejected')->set('search', 'Unrelated')
        ->call('toggleRepeatedRevisions')->assertSet('status', '')->assertSet('search', '')
        ->assertViewHas('topics', fn ($topics) => $topics->total() === 1 && $topics->first()->id === $old->id)
        ->set('call', '999999')->assertViewHas('topics', fn ($topics) => $topics->total() === 0);
});
