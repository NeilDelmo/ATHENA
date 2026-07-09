<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_templates', function (Blueprint $table) {
            $table->string('workflow_stage')->default('initial_submission')->after('revision_label')->index();
        });

        $now = now();

        collect(config('proposal_templates', []))
            ->filter(fn (array $template) => isset($template['workflow_stage']))
            ->each(function (array $template, string $slug) use ($now) {
                DB::table('proposal_templates')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $template['name'],
                        'description' => $template['description'] ?? null,
                        'instructions' => $template['instructions'] ?? null,
                        'revision_label' => null,
                        'workflow_stage' => $template['workflow_stage'],
                        'file_path' => $template['path'],
                        'original_filename' => basename($template['path']),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        DB::table('proposal_templates')->whereIn('slug', [
            'gad-generic-checklist',
            'initial-screening-form',
            'lrec-comment-response-form',
        ])->delete();

        Schema::table('proposal_templates', function (Blueprint $table) {
            $table->dropIndex(['workflow_stage']);
            $table->dropColumn('workflow_stage');
        });
    }
};
