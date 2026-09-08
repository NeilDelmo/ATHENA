<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->timestamp('status_started_at')->nullable()->index();
        });
        Schema::create('proposal_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('previous_started_at')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['from_status', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_stage_transitions');
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('status_started_at'));
    }
};
