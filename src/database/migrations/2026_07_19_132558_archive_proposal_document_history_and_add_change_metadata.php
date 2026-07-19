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
        Schema::table('proposal_draft_document_versions', function (Blueprint $table) {
            $table->dropForeign('draft_doc_versions_draft_fk');
            $table->unsignedBigInteger('proposal_draft_id')->nullable()->change();
            $table->foreign('proposal_draft_id', 'draft_doc_versions_draft_fk')
                ->references('id')
                ->on('proposal_drafts')
                ->nullOnDelete();

            $table->foreignId('topic_id')
                ->nullable()
                ->after('proposal_draft_id')
                ->constrained('topics', indexName: 'draft_doc_versions_topic_fk')
                ->cascadeOnDelete();
            $table->string('action', 40)->default('saved')->after('is_current');
            $table->string('change_note', 500)->nullable()->after('action');
            $table->string('change_summary', 500)->nullable()->after('change_note');
            $table->json('changes')->nullable()->after('change_summary');
            $table->foreignId('restored_from_version_id')
                ->nullable()
                ->after('changes')
                ->constrained('proposal_draft_document_versions', indexName: 'draft_doc_versions_restored_fk')
                ->nullOnDelete();

            $table->index(['topic_id', 'created_at'], 'draft_doc_versions_topic_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_document_versions', function (Blueprint $table) {
            $table->dropForeign('draft_doc_versions_restored_fk');
            $table->dropForeign('draft_doc_versions_topic_fk');
            $table->dropIndex('draft_doc_versions_topic_created_index');
            $table->dropColumn([
                'topic_id',
                'action',
                'change_note',
                'change_summary',
                'changes',
                'restored_from_version_id',
            ]);

            $table->dropForeign('draft_doc_versions_draft_fk');
            $table->foreign('proposal_draft_id', 'draft_doc_versions_draft_fk')
                ->references('id')
                ->on('proposal_drafts')
                ->cascadeOnDelete();
        });
    }
};
