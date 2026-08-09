<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('literature_sources', function (Blueprint $table) {
            $table->string('access_status', 24)->default('unknown')->index()->after('is_open_access');
            $table->text('full_text_url')->nullable()->after('url');
            $table->string('provider_identifier', 255)->nullable()->after('provider');
            $table->string('volume', 100)->nullable()->after('venue');
            $table->string('issue', 100)->nullable()->after('volume');
            $table->string('pages', 100)->nullable()->after('issue');
            $table->string('publisher', 500)->nullable()->after('pages');
            $table->date('publication_date')->nullable()->after('publication_year');
        });

        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->string('access_status', 24)->default('unknown')->after('is_open_access');
            $table->text('full_text_url')->nullable()->after('url');
            $table->string('provider_identifier', 255)->nullable()->after('provider');
            $table->string('volume', 100)->nullable()->after('venue');
            $table->string('issue', 100)->nullable()->after('volume');
            $table->string('pages', 100)->nullable()->after('issue');
            $table->string('publisher', 500)->nullable()->after('pages');
            $table->date('publication_date')->nullable()->after('publication_year');
            $table->string('rrl_draft_status', 20)->default('none')->after('rrl_note');
            $table->string('rrl_evidence_basis', 20)->nullable()->after('rrl_draft_status');
            $table->unsignedSmallInteger('rrl_word_count')->nullable()->after('rrl_evidence_basis');
            $table->timestamp('rrl_generated_at')->nullable()->after('rrl_word_count');
        });

        DB::table('proposal_draft_literature_sources')
            ->whereNotNull('rrl_note')
            ->where('rrl_note', '!=', '')
            ->update([
                'rrl_draft_status' => 'confirmed',
                'rrl_evidence_basis' => 'abstract',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->dropColumn([
                'access_status', 'full_text_url', 'provider_identifier', 'volume', 'issue', 'pages',
                'publisher', 'publication_date', 'rrl_draft_status', 'rrl_evidence_basis',
                'rrl_word_count', 'rrl_generated_at',
            ]);
        });

        Schema::table('literature_sources', function (Blueprint $table) {
            $table->dropIndex('literature_sources_access_status_index');
            $table->dropColumn([
                'access_status', 'full_text_url', 'provider_identifier', 'volume', 'issue', 'pages',
                'publisher', 'publication_date',
            ]);
        });
    }
};
