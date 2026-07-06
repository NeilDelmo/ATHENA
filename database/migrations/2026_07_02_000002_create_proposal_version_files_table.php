<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_versions', function (Blueprint $table) {
            $table->text('change_summary')->nullable()->after('submission_type');
        });

        Schema::create('proposal_version_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_version_file_id')->nullable()->constrained('proposal_version_files')->nullOnDelete();
            $table->string('document_type');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->boolean('is_carried_forward')->default(false);
            $table->timestamps();

            $table->unique(['proposal_version_id', 'document_type', 'position'], 'proposal_version_file_slot_unique');
            $table->index(['proposal_version_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_version_files');

        Schema::table('proposal_versions', function (Blueprint $table) {
            $table->dropColumn('change_summary');
        });
    }
};
