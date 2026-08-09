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
        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->json('required_signature_file_ids')->nullable()->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topic_reviews', function (Blueprint $table) {
            $table->dropColumn('required_signature_file_ids');
        });
    }
};
