<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'lock_version',
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
            'lock_version' => 'integer',
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

    public function documentVersions(): HasMany
    {
        return $this->hasMany(ProposalDraftDocumentVersion::class)
            ->latest();
    }

    public function currentDocumentVersion(
        string $documentType,
        int $position,
        ?ProposalDraftDocument $document = null,
    ): int {
        $latestRecordedVersion = (int) $this->documentVersions()
            ->reorder()
            ->where('document_type', $documentType)
            ->where('position', $position)
            ->max('version_number');

        return max($document?->lock_version ?? 0, $latestRecordedVersion);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProposalDraftMember::class)
            ->orderBy('name');
    }

    /** @param Builder<ProposalDraft> $query */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $accessible) use ($user): void {
            $accessible
                ->where('user_id', $user->getKey())
                ->orWhereHas('members', fn (Builder $members): Builder => $members->forUser($user));
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->getKey();
    }

    public function isSharedWith(User $user): bool
    {
        return $this->members()->forUser($user)->exists();
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
