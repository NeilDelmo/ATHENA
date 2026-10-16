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
        foreach (['proposal_drafts', 'topics'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('research_manual_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['proposal_drafts', 'topics'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('research_manual_id');
            });
        }
    }
};
