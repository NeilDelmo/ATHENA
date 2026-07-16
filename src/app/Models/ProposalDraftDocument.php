<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'source_data' => 'array',
            'file_size' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ProposalDraft::class, 'proposal_draft_id');
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
