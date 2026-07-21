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
            $table->foreignId('uploaded_by')->nullable()->after('is_carried_forward')->constrained('users')->nullOnDelete();
            $table->index(['proposal_version_id', 'uploaded_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_version_files', function (Blueprint $table) {
            $table->dropIndex(['proposal_version_id', 'uploaded_by']);
            $table->dropConstrainedForeignId('uploaded_by');
        });
    }
};
