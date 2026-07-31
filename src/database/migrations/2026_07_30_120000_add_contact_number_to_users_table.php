<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'contact_number')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 11)->nullable()->after('college');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'contact_number')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('contact_number');
        });
    }
};
