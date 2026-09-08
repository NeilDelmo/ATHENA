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
        Schema::create('proposal_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('role_key', 80)->index();
            $table->string('name', 120);
            $table->string('position', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('proposal_drafts', fn (Blueprint $table) => $table->json('signatory_selections')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_signatories');
        Schema::table('proposal_drafts', fn (Blueprint $table) => $table->dropColumn('signatory_selections'));
    }
};
