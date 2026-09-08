<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->string('feedback_source', 24)->default('research_head');
            $table->string('co_evaluator_name', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->dropColumn(['feedback_source', 'co_evaluator_name']);
        });
    }
};
