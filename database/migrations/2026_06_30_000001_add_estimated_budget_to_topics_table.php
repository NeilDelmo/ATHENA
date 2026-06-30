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
        if (Schema::hasColumn('topics', 'estimated_budget')) {
            return;
        }

        Schema::table('topics', function (Blueprint $table) {
            $table->decimal('estimated_budget', 12, 2)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('topics', 'estimated_budget')) {
            return;
        }

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('estimated_budget');
        });
    }
};
