<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_calls', function (Blueprint $table) {
            $table->timestamp('opening_reminder_sent_at')->nullable()->after('status');
            $table->timestamp('faculty_open_notification_sent_at')->nullable()->after('opening_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('research_calls', function (Blueprint $table) {
            $table->dropColumn(['opening_reminder_sent_at', 'faculty_open_notification_sent_at']);
        });
    }
};
