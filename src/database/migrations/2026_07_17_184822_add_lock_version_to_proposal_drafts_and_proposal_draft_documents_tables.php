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
        Schema::table('proposal_drafts', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0)->after('status');
        });

        Schema::table('proposal_draft_documents', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0)->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_documents', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });

        Schema::table('proposal_drafts', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
