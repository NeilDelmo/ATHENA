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
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->string('submission_status')->default('submitted')->index()->after('attachment_path');
            $table->string('official_pdf_path')->nullable()->after('submission_status');
            $table->string('official_pdf_filename')->nullable()->after('official_pdf_path');
            $table->string('official_pdf_checksum', 64)->nullable()->after('official_pdf_filename');
            $table->unsignedBigInteger('official_pdf_size')->nullable()->after('official_pdf_checksum');
            $table->timestamp('prepared_at')->nullable()->after('official_pdf_size');
            $table->timestamp('submitted_at')->nullable()->after('prepared_at');
        });

        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->string('submission_status')->default('submitted')->index()->after('photos');
            $table->string('official_pdf_path')->nullable()->after('submission_status');
            $table->string('official_pdf_filename')->nullable()->after('official_pdf_path');
            $table->string('official_pdf_checksum', 64)->nullable()->after('official_pdf_filename');
            $table->unsignedBigInteger('official_pdf_size')->nullable()->after('official_pdf_checksum');
            $table->timestamp('prepared_at')->nullable()->after('official_pdf_size');
            $table->timestamp('submitted_at')->nullable()->after('prepared_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->dropIndex(['submission_status']);
            $table->dropColumn([
                'submission_status',
                'official_pdf_path',
                'official_pdf_filename',
                'official_pdf_checksum',
                'official_pdf_size',
                'prepared_at',
                'submitted_at',
            ]);
        });

        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->dropIndex(['submission_status']);
            $table->dropColumn([
                'submission_status',
                'official_pdf_path',
                'official_pdf_filename',
                'official_pdf_checksum',
                'official_pdf_size',
                'prepared_at',
                'submitted_at',
            ]);
        });
    }
};
