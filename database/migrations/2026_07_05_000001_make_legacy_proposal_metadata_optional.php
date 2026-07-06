<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->decimal('estimated_budget', 12, 2)->nullable()->change();
            $table->unsignedSmallInteger('estimated_duration_months')->nullable()->change();
        });

        Schema::table('proposal_versions', function (Blueprint $table) {
            $table->decimal('estimated_budget', 12, 2)->nullable()->change();
            $table->unsignedSmallInteger('estimated_duration_months')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Historical metadata is intentionally retained and remains optional.
    }
};
