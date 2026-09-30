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
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->string('lrec_reviewer_name', 160)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->dropColumn('lrec_reviewer_name');
        });
    }
};
