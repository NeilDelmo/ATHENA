<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->string('review_stage', 20)->default('initial');
            $table->timestamp('lrec_cleared_at')->nullable();
        });
        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->string('review_stage', 20)->default('initial');
            $table->json('committee_comments')->nullable();
            $table->json('feedback_responses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->dropColumn(['review_stage', 'committee_comments', 'feedback_responses']);
        });
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn(['review_stage', 'lrec_cleared_at']);
        });
    }
};
