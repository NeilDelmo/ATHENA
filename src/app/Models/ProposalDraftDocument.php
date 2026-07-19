<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
