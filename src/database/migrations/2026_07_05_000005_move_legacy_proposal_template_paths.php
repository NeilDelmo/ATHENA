<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        collect(config('proposal_templates', []))->each(function (array $template, string $slug) {
            DB::table('proposal_templates')
                ->where('slug', $slug)
                ->where('file_path', 'like', 'proposals/%')
                ->where('file_path', 'not like', 'proposals/templates/%')
                ->update([
                    'file_path' => $template['path'],
                    'original_filename' => basename($template['path']),
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        collect(config('proposal_templates', []))->each(function (array $template, string $slug) {
            DB::table('proposal_templates')
                ->where('slug', $slug)
                ->where('file_path', $template['path'])
                ->update([
                    'file_path' => str_replace('proposals/templates/', 'proposals/', $template['path']),
                    'updated_at' => now(),
                ]);
        });
    }
};
