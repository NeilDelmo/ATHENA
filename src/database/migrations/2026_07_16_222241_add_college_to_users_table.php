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
        if (Schema::hasColumn('users', 'college')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('college')->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'college')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('college');
        });
    }
};
