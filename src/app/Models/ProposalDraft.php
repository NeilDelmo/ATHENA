<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalDraft extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTING = 'submitting';

    protected $fillable = [
        'user_id',
        'research_call_id',
        'project_title',
        'duration_months',
        'planned_start',
        'planned_end',
        'project_leader',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'planned_start' => 'date',
            'planned_end' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProposalDraftDocument::class)
            ->orderBy('document_type')
            ->orderBy('position');
    }

    public function projectDetailsAreComplete(): bool
    {
        return filled($this->project_title)
            && $this->duration_months !== null
            && $this->duration_months > 0
            && $this->planned_start !== null
            && $this->planned_end !== null
            && filled($this->project_leader);
    }

    public function storageDirectory(): string
    {
        return "proposal-drafts/{$this->user_id}/{$this->getKey()}";
    }
}
