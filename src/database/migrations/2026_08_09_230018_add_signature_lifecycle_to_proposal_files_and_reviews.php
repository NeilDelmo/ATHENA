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
        Schema::table('proposal_version_files', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->index()->after('uploaded_by');
            $table->foreignId('superseded_by_version_file_id')
                ->nullable()
                ->after('superseded_at')
                ->constrained('proposal_version_files')
                ->nullOnDelete();
        });

        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->foreignId('signature_proposal_version_id')
                ->nullable()
                ->after('required_signature_file_ids')
                ->constrained('proposal_versions')
                ->nullOnDelete();
            $table->timestamp('signature_superseded_at')->nullable()->index()->after('signature_proposal_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->dropIndex(['signature_superseded_at']);
            $table->dropConstrainedForeignId('signature_proposal_version_id');
            $table->dropColumn('signature_superseded_at');
        });

        Schema::table('proposal_version_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_version_file_id');
            $table->dropIndex(['superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
