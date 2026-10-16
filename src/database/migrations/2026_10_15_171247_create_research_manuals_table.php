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
        Schema::create('research_manuals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('edition', 100);
            $table->date('effective_date')->nullable();
            $table->string('version', 64)->unique();
            $table->string('directory');
            $table->unsignedInteger('page_count');
            $table->json('unindexed_pages');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_manuals');
    }
};
