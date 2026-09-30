<?php

namespace App\Models;

use Database\Factories\ProjectJournalSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectJournalSubmission extends Model
{
    /** @use HasFactory<ProjectJournalSubmissionFactory> */
    use HasFactory;

    public const STATUSES = [
        'shortlisted' => 'Shortlisted',
        'submitted' => 'Submitted to journal',
        'under_review' => 'Under journal review',
        'revision_requested' => 'Journal requested revisions',
        'accepted' => 'Accepted by journal',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
    ];

    protected $attributes = ['status' => 'shortlisted'];

    protected $fillable = [
        'topic_id', 'added_by', 'fingerprint', 'journal_name', 'issn', 'journal_url',
        'manuscript_title', 'status', 'submission_reference', 'submitted_on',
        'accepted_on', 'published_on', 'publication_url', 'notes',
    ];

    protected function casts(): array
    {
        return ['submitted_on' => 'date', 'accepted_on' => 'date', 'published_on' => 'date'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
