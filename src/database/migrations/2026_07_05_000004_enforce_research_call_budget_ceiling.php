<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('research_calls')
            ->whereNull('maximum_budget')
            ->orWhere('maximum_budget', '>', 150000)
            ->update(['maximum_budget' => 150000]);
    }

    public function down(): void
    {
        // Existing values cannot be reconstructed after applying the institutional ceiling.
    }
};
