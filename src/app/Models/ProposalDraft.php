<?php

namespace App\Models;

use App\Services\ResearchManualLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalDraft extends Model
{
    use SoftDeletes;

    public const TRASH_RETENTION_DAYS = 30;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTING = 'submitting';

    public const PENDING_DELETION_SUBMISSION_MESSAGE = 'Resolve the open deletion request before submitting this proposal.';

    protected $fillable = [
        'user_id',
        'research_call_id',
        'research_manual_id',
        'topic_id',
        'project_title',
        'duration_months',
        'planned_start',
        'planned_end',
        'project_leader',
        'status',
        'lock_version',
        'signatory_selections',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'shared_trash' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (ProposalDraft $draft): void {
            if (! array_key_exists('research_manual_id', $draft->getAttributes())) {
                $draft->research_manual_id = app(ResearchManualLibrary::class)->active()?->id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'shared_trash' => 'boolean',
            'duration_months' => 'integer',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'lock_version' => 'integer',
            'signatory_selections' => 'array',
            'pdf_preparation_state' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function signatoryFields(string $paper): array
    {
        $fields = [];
        $selections = $this->resolvedSignatorySelections();
        foreach (ProposalSignatory::FIELDS[$paper] ?? [] as $key => $label) {
            $selection = $selections[$key] ?? null;
            if ($selection === null) {
                continue;
            }
            $fields[$key] = $selection['name'];
            $roleKey = ['verified_by' => 'verified_role', 'certified_by' => 'certified_role', 'verifier_name' => 'verifier_role'][$key] ?? null;
            if ($roleKey) {
                $fields[$roleKey] = $selection['position'];
            }
        }

        return $fields;
    }

    /** @return array<string, array{id?: int|null, name: string, position: string}> */
    public function resolvedSignatorySelections(): array
    {
        return $this->exists ? ProposalSignatory::managedDefaultSelections() : ProposalSignatory::defaultSelections();
    }

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function researchManual(): BelongsTo
    {
        return $this->belongsTo(ResearchManual::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function isEditable(): bool
    {
        if ($this->trashed() || $this->status !== self::STATUS_DRAFT) {
            return false;
        }

        if ($this->topic_id === null) {
            return true;
        }

        $topic = $this->topic()->first();

        return $topic !== null && ($topic->status === 'revision_requested' || $topic->canUpdateBeforeReview());
    }

    public function ensureEditable(): void
    {
        abort_if($this->trashed(), 403);

        if ($this->topic_id !== null) {
            $topic = $this->topic()->lockForUpdate()->first();
            abort_unless($topic !== null && ($topic->status === 'revision_requested' || $topic->canUpdateBeforeReview()), 403);
        }

        abort_unless($this->status === self::STATUS_DRAFT, 403);
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

    public function submittedDocumentVersions(): HasMany
    {
        return $this->documentVersions()->whereNotNull('topic_id');
    }

    public function isLinkedToSubmission(): bool
    {
        return $this->topic_id !== null
            || ($this->submitted_document_versions_exists ?? $this->submittedDocumentVersions()->exists());
    }

    public function literatureSources(): HasMany
    {
        return $this->hasMany(ProposalDraftLiteratureSource::class)
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

    public function acceptedMembers(): HasMany
    {
        return $this->members()->whereNotNull('accepted_at');
    }

    public function hasAcceptedMembers(): bool
    {
        return $this->accepted_members_exists ?? $this->acceptedMembers()->exists();
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(ProposalDraftDeletionRequest::class);
    }

    public function pendingDeletionRequest(): HasOne
    {
        return $this->hasOne(ProposalDraftDeletionRequest::class)->where('status', ProposalDraftDeletionRequest::STATUS_PENDING);
    }

    public function hasPendingDeletionRequest(): bool
    {
        return $this->relationLoaded('pendingDeletionRequest')
            ? $this->pendingDeletionRequest !== null
            : $this->exists && $this->pendingDeletionRequest()->exists();
    }

    public function trashRecoveryIsOpen(): bool
    {
        return $this->trashed()
            && ($this->deleted_at->greaterThan(now()->subDays(self::TRASH_RETENTION_DAYS)) || $this->isLinkedToSubmission());
    }

    /** @param Builder<ProposalDraft> $query */
    public function scopeTrashAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $trash) use ($user): void {
            $trash->whereBelongsTo($user, 'owner')
                ->orWhere(fn (Builder $shared): Builder => $shared->where('shared_trash', true)
                    ->whereHas('members', fn (Builder $members): Builder => $members->forUser($user)));
        });
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
