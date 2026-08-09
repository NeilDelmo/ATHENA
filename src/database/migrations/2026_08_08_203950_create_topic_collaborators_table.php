<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('topic_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'user_id'], 'topic_collaborator_user_unique');
            $table->unique(['topic_id', 'email'], 'topic_collaborator_email_unique');
        });

        DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('data', 'like', '%Proposal submitted for review%')
            ->orderBy('created_at')
            ->get(['notifiable_id', 'data', 'created_at'])
            ->each(function (object $notification): void {
                $data = json_decode((string) $notification->data, true);
                $topicId = $data['topic_id'] ?? null;

                if (($data['title'] ?? null) !== 'Proposal submitted for review' || ! is_numeric($topicId)) {
                    return;
                }

                $user = DB::table('users')->find($notification->notifiable_id, ['id', 'name', 'email']);

                if (! $user || ! DB::table('topics')->where('id', $topicId)->exists()) {
                    return;
                }

                DB::table('topic_collaborators')->insertOrIgnore([
                    'topic_id' => (int) $topicId,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => mb_strtolower(trim($user->email)),
                    'accepted_at' => $notification->created_at,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->created_at,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_collaborators');
    }
};
