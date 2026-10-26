<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProposalDraftDocument extends Model
{
    protected $fillable = [
        'proposal_draft_id',
        'document_type',
        'position',
        'source_data',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'completed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'source_data' => 'array',
            'file_size' => 'integer',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ProposalDraft::class, 'proposal_draft_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProposalDraftDocumentVersion::class)
            ->orderByDesc('version_number');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function hasStagedFile(): bool
    {
        return filled($this->file_path);
    }

    public function hasCurrentPreparedPdf(ProposalDraft $draft): bool
    {
        if ($this->mime_type !== 'application/pdf' || blank($this->file_path)
            || ! Storage::disk('local')->exists($this->file_path)) {
            return false;
        }

        if ($this->document_type !== ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN
            || config('proposal_papers.expense-breakdown.mode') !== 'generated'
            || ! is_array($this->source_data['items'] ?? null)
            || ! str_starts_with($this->file_path, $draft->storageDirectory().'/prepared/')
            || str_contains($this->file_path, '/prepared/manual/')
            || ! $draft->isEditable()) {
            return true;
        }

        return ($this->source_data['pdf_layout_version'] ?? null) === config('expense_breakdown.pdf_layout_version');
    }
}
