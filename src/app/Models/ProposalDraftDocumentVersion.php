<?php

namespace App\Models;

use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalDraftDocumentVersion extends Model
{
    protected $fillable = [
        'proposal_draft_id',
        'proposal_draft_document_id',
        'created_by',
        'document_type',
        'position',
        'version_number',
        'is_current',
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
            'version_number' => 'integer',
            'is_current' => 'boolean',
            'source_data' => 'array',
            'file_size' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ProposalDraft::class, 'proposal_draft_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProposalDraftDocument::class, 'proposal_draft_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return app(ProposalPaperCatalog::class)->label($this->document_type)
            ?? str($this->document_type)->replace('_', ' ')->title()->toString();
    }

    public function hasStoredFile(): bool
    {
        return filled($this->file_path);
    }

    public function isCurrent(): bool
    {
        return $this->is_current && $this->document !== null;
    }
}
