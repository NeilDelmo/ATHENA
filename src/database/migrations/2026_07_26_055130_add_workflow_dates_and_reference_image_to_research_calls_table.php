<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_calls', function (Blueprint $table) {
            $table->string('reference_image_path')->nullable()->after('description');
            $table->date('initial_evaluation_start_date')->nullable()->after('closes_at');
            $table->date('initial_evaluation_end_date')->nullable()->after('initial_evaluation_start_date');
            $table->date('paper_revisions_start_date')->nullable()->after('initial_evaluation_end_date');
            $table->date('paper_revisions_end_date')->nullable()->after('paper_revisions_start_date');
            $table->date('lrec_start_date')->nullable()->after('paper_revisions_end_date');
            $table->date('lrec_end_date')->nullable()->after('lrec_start_date');
            $table->date('implementation_start_date')->nullable()->after('lrec_end_date');
            $table->date('implementation_end_date')->nullable()->after('implementation_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('research_calls', function (Blueprint $table) {
            $table->dropColumn([
                'reference_image_path',
                'initial_evaluation_start_date',
                'initial_evaluation_end_date',
                'paper_revisions_start_date',
                'paper_revisions_end_date',
                'lrec_start_date',
                'lrec_end_date',
                'implementation_start_date',
                'implementation_end_date',
            ]);
        });
    }
};
