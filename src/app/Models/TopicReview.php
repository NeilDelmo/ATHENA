<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicReview extends Model
{
    protected $fillable = [
        'reviewer_id',
        'decision',
        'comment',
        'required_signature_file_ids',
        'signature_proposal_version_id',
        'signature_superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'required_signature_file_ids' => 'array',
            'signature_superseded_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function fileRevisions(): HasMany
    {
        return $this->hasMany(TopicReviewFileRevision::class);
    }

    public function signatureProposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'signature_proposal_version_id');
    }
}
