<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_call_deadline_dismissals')) {
            Schema::create('research_call_deadline_dismissals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('research_call_id')->constrained()->cascadeOnDelete();
                $table->date('dismissed_on');
                $table->timestamp('deadline_at');
                $table->timestamps();
            });
        }

        Schema::table('research_call_deadline_dismissals', function (Blueprint $table) {
            $table->unique(['user_id', 'research_call_id'], 'research_call_deadline_user_call_unique');
            $table->index(['user_id', 'dismissed_on'], 'research_call_deadline_user_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_call_deadline_dismissals');
    }
};
