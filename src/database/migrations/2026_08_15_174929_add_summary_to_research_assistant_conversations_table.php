<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_assistant_conversations', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('context');
            $table->unsignedSmallInteger('summarized_message_count')->default(0)->after('summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_assistant_conversations', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summarized_message_count']);
        });
    }
};
