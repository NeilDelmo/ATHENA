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
        Schema::table('proposal_drafts', function (Blueprint $table): void {
            $table->foreignId('topic_id')
                ->nullable()
                ->after('research_call_id')
                ->constrained('topics')
                ->nullOnDelete();

            $table->unique('topic_id', 'proposal_drafts_topic_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_drafts', function (Blueprint $table): void {
            $table->dropUnique('proposal_drafts_topic_id_unique');
            $table->dropConstrainedForeignId('topic_id');
        });
    }
};
