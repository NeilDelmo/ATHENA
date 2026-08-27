<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_drafts', function (Blueprint $table): void {
            $table->foreignId('research_call_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_drafts', function (Blueprint $table): void {
            $table->foreignId('research_call_id')->nullable(false)->change();
        });
    }
};
