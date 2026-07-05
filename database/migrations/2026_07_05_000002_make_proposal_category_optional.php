<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedBigInteger('research_category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing uncategorized proposals must remain valid after rollback.
    }
};
