<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('research_calls', 'max_proposals_per_faculty')) {
            Schema::table('research_calls', function (Blueprint $table) {
                $table->renameColumn('max_proposals_per_faculty', 'max_active_research_per_faculty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('research_calls', 'max_active_research_per_faculty')) {
            Schema::table('research_calls', function (Blueprint $table) {
                $table->renameColumn('max_active_research_per_faculty', 'max_proposals_per_faculty');
            });
        }
    }
};
