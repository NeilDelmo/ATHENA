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
        Schema::table('topics', function (Blueprint $table) {
            $table->string('notice_to_proceed_path')->nullable()->after('signed_approval_path');
            $table->string('notice_to_proceed_original_filename')->nullable()->after('notice_to_proceed_path');
            $table->foreignId('notice_to_proceed_issued_by')
                ->nullable()
                ->after('notice_to_proceed_original_filename')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('notice_to_proceed_issued_at')
                ->nullable()
                ->after('notice_to_proceed_issued_by')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notice_to_proceed_issued_by');
            $table->dropIndex(['notice_to_proceed_issued_at']);
            $table->dropColumn([
                'notice_to_proceed_path',
                'notice_to_proceed_original_filename',
                'notice_to_proceed_issued_at',
            ]);
        });
    }
};
