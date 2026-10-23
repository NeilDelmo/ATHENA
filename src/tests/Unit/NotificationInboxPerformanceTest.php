<?php

use App\Models\User;
use App\Services\NotificationInbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

class NotificationInboxPresentationDate extends Carbon
{
    public static int $formatted = 0;

    public function diffForHumans($other = null, $syntax = null, $short = false, $parts = 1, $options = null): string
    {
        self::$formatted++;

        return parent::diffForHumans($other, $syntax, $short, $parts, $options);
    }
}

class NotificationInboxPerformanceNotification extends DatabaseNotification
{
    public function getCreatedAtAttribute(string $value): Carbon
    {
        return new NotificationInboxPresentationDate($value);
    }
}

test('the header formats only its fifteen displayed groups while preserving grouped history and ordering', function () {
    $this->freezeTime();
    $notifications = new Collection;
    foreach ([100, ...range(750, 101), ...range(99, 1)] as $number) {
        $notifications->push(new NotificationInboxPerformanceNotification([
            'id' => sprintf('00000000-0000-4000-8000-%012d', $number),
            'type' => 'proposal_activity',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => ['title' => 'Revision requested', 'message' => 'Update '.$number,
                'topic_id' => $number >= 699 && $number !== 725 ? 42 : 1000 + $number],
            'read_at' => $number % 2 === 0 ? now()->subMinute() : null,
            'created_at' => $number === 100 ? now()->addMinute() : now(),
            'updated_at' => now(),
        ]));
    }
    $query = Mockery::mock(Builder::class)->makePartial();
    $query->shouldReceive('latest', 'orderByDesc', 'limit')->andReturnSelf();
    $query->shouldReceive('get')->andReturn($notifications);
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('visibleNotificationsQuery')->andReturn($query);
    $inbox = app(NotificationInbox::class);

    NotificationInboxPresentationDate::$formatted = 0;
    $header = $inbox->for($user, [], recentOnly: true);

    expect(NotificationInboxPresentationDate::$formatted)->toBe(64)
        ->and($header)->toHaveCount(15)
        ->and($header->pluck('id')->all())->toBe(array_map(
            fn (int $number): string => sprintf('00000000-0000-4000-8000-%012d', $number),
            [100, 750, 725, 699, ...range(698, 688)],
        ))
        ->and($header[1]['update_count'])->toBe(50)
        ->and($header[1]['unread_count'])->toBe(24)
        ->and($header[1]['read_at'])->toBeNull()
        ->and($header[1]['history'])->toHaveCount(50)
        ->and($header[3]['update_count'])->toBe(1);

    NotificationInboxPresentationDate::$formatted = 0;
    $fullInbox = $inbox->for($user, []);

    expect(NotificationInboxPresentationDate::$formatted)->toBe(750)
        ->and($fullInbox)->toHaveCount(701)
        ->and($header->all())->toBe($fullInbox->take(15)->all());
});
