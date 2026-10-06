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
        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->json('evidence_document')->nullable();
            $table->json('evidence_passages')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->dropColumn(['evidence_document', 'evidence_passages']);
        });
    }
};
