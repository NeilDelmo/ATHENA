<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pendingTopicIds = DB::table('topics')
            ->whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision'])
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        if ($pendingTopicIds === []) {
            return;
        }

        DB::table('notifications')
            ->whereNotNull('read_at')
            ->orderBy('id')
            ->chunk(100, function ($notifications) use ($pendingTopicIds): void {
                $notificationIds = $notifications
                    ->filter(function (object $notification) use ($pendingTopicIds): bool {
                        $data = json_decode((string) $notification->data, true);

                        return is_array($data)
                            && ($data['workspace'] ?? null) === 'research_head'
                            && ($data['sidebar_area'] ?? null) === 'proposal_submissions'
                            && in_array((int) ($data['topic_id'] ?? 0), $pendingTopicIds, true);
                    })
                    ->pluck('id');

                if ($notificationIds->isNotEmpty()) {
                    DB::table('notifications')
                        ->whereIn('id', $notificationIds)
                        ->update(['read_at' => null]);
                }
            });
    }

    public function down(): void
    {
        // The prior read timestamps cannot be reconstructed safely.
    }
};
