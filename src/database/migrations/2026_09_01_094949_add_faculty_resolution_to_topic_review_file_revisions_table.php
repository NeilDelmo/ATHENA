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
        Schema::table('topic_review_file_revisions', function (Blueprint $table) {
            $table->string('resolution_type')->nullable()->after('revision_note');
            $table->text('faculty_response')->nullable()->after('resolution_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topic_review_file_revisions', function (Blueprint $table) {
            $table->dropColumn(['resolution_type', 'faculty_response']);
        });
    }
};
