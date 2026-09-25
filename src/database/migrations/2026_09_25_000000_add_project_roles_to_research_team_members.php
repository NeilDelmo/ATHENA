<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_draft_members', function (Blueprint $table) {
            $table->string('project_role')->nullable()->after('accepted_at');
        });

        Schema::table('topic_collaborators', function (Blueprint $table) {
            $table->string('project_role')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('topic_collaborators', function (Blueprint $table) {
            $table->dropColumn('project_role');
        });

        Schema::table('proposal_draft_members', function (Blueprint $table) {
            $table->dropColumn('project_role');
        });
    }
};
