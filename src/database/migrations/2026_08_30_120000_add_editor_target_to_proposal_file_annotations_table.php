<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->string('editor_target')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_file_annotations', function (Blueprint $table) {
            $table->dropColumn('editor_target');
        });
    }
};
