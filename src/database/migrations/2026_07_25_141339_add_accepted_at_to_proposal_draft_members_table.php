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
        Schema::table('proposal_draft_members', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->useCurrent()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_members', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
