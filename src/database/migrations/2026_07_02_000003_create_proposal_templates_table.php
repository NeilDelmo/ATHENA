<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('revision_label')->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        $now = now();
        $templates = collect(config('proposal_templates', []))->map(fn (array $template, string $slug) => [
            'slug' => $slug,
            'name' => $template['name'],
            'description' => $template['description'] ?? null,
            'instructions' => null,
            'revision_label' => null,
            'file_path' => $template['path'],
            'original_filename' => basename($template['path']),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        if ($templates !== []) {
            DB::table('proposal_templates')->insert($templates);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_templates');
    }
};
