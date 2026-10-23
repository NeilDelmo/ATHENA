<?php

use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\SidebarAttentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
});

test('workspaces without sidebar badges skip unread notification queries', function (string $role, string $workspace) {
    Role::firstOrCreate(['name' => $role]);
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $counts = app(SidebarAttentionService::class)->countsFor($user);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($counts)->toBe([])
        ->and($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `notifications`')))->toHaveCount(0);
})->with([
    ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY],
]);

test('the notification page shares its unread count with the header only within each request', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');
    foreach (range(1, 2) as $number) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ProposalActivityNotification::class,
            'data' => ['title' => 'Update '.$number, 'workspace' => User::WORKSPACE_FACULTY],
        ]);
    }
    $this->actingAs($user);

    foreach ([2, 1] as $expectedCount) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('notifications.index'));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertViewHas('unreadCount', $expectedCount)
            ->assertSee('unreadCount: '.$expectedCount, false);
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `notifications`')
            && str_contains($query['query'], 'count(*)')))->toHaveCount(1);

        $user->notifications()->first()->markAsRead();
    }
});

test('marking all notifications read uses one update and preserves hidden and previously read notifications', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $otherUser = User::factory()->create();
    $rows = collect(range(1, 40))->map(fn (int $number): array => [
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $number === 40 ? $otherUser->id : $user->id,
        'data' => json_encode(['title' => 'Update '.$number, 'workspace' => $number === 39 ? User::WORKSPACE_RESEARCH_HEAD : User::WORKSPACE_FACULTY], JSON_THROW_ON_ERROR),
        'read_at' => $number === 38 ? now()->subDay()->toDateTimeString() : null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);
    $user->notifications()->insert($rows->all());
    $this->actingAs($user);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->patchJson(route('notifications.read-all'));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()->assertJsonPath('unread_count', 0);
    $notificationQueries = $queries->filter(fn (array $query): bool => str_contains($query['query'], '`notifications`'));
    expect($notificationQueries)->toHaveCount(1)
        ->and($notificationQueries->first()['query'])->toStartWith('update ')
        ->and($user->notifications()->whereIn('id', $rows->take(37)->pluck('id'))->whereNotNull('read_at')->count())->toBe(37)
        ->and($user->notifications()->find($rows[37]['id'])->read_at->toDateTimeString())->toBe($rows[37]['read_at'])
        ->and($user->notifications()->find($rows[38]['id'])->read_at)->toBeNull()
        ->and($otherUser->notifications()->find($rows[39]['id'])->read_at)->toBeNull();
});
