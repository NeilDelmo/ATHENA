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
        Schema::create('literature_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('fingerprint', 64)->unique();
            $table->string('title', 500);
            $table->text('authors')->nullable();
            $table->text('abstract')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable()->index();
            $table->string('venue', 500)->nullable();
            $table->string('doi')->nullable()->index();
            $table->text('url')->nullable();
            $table->string('provider', 100);
            $table->unsignedInteger('citation_count')->nullable();
            $table->boolean('is_open_access')->default(false);
            $table->string('publication_type', 100)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'id']);
        });

        Schema::create('literature_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['name', 'id']);
        });

        Schema::create('literature_collection_source', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literature_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('literature_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['literature_collection_id', 'literature_source_id'],
                'literature_collection_source_unique',
            );
        });

        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->foreignId('literature_source_id')
                ->nullable()
                ->after('proposal_draft_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->longText('rrl_note')->nullable()->after('publication_type');
            $table->text('reference_text')->nullable()->after('rrl_note');
            $table->unique(
                ['proposal_draft_id', 'literature_source_id'],
                'proposal_shared_literature_source_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->dropUnique('proposal_shared_literature_source_unique');
            $table->dropConstrainedForeignId('literature_source_id');
            $table->dropColumn(['rrl_note', 'reference_text']);
        });

        Schema::dropIfExists('literature_collection_source');
        Schema::dropIfExists('literature_collections');
        Schema::dropIfExists('literature_sources');
    }
};
