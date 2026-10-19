<?php

namespace App\Models;

use Database\Factories\ResearchManualFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ResearchManual extends Model
{
    /** @use HasFactory<ResearchManualFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'edition', 'effective_date', 'version', 'directory', 'page_count',
        'unindexed_pages', 'uploaded_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'page_count' => 'integer',
            'unindexed_pages' => 'array',
            'activated_at' => 'datetime',
            'guidance_check_results' => 'array',
            'guidance_check_started_at' => 'datetime',
            'guidance_check_completed_at' => 'datetime',
        ];
    }

    public function indexPath(): string
    {
        return Storage::disk('local')->path($this->directory.'/index.json');
    }

    public function pdfPath(): string
    {
        return Storage::disk('local')->path($this->directory.'/manual.pdf');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
