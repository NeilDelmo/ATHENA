<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('research_calls')->update(['maximum_budget' => 150000]);
    }

    public function down(): void
    {
        // Previous per-call limits cannot be reconstructed after normalization.
    }
};
