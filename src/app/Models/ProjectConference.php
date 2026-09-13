<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectConference extends Model
{
    use HasFactory;

    public const STATUSES = ['shortlisted', 'submitted', 'accepted', 'presented', 'rejected', 'withdrawn'];

    protected $attributes = ['status' => 'shortlisted', 'source' => 'Researcher entry'];

    protected $fillable = [
        'topic_id', 'added_by', 'fingerprint', 'title', 'url', 'official_url', 'source',
        'location', 'submission_deadline', 'event_date', 'attendance_mode', 'fees',
        'publication_details', 'source_checked_at', 'status', 'submitted_on',
        'accepted_on', 'presented_on', 'evidence_url', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'submission_deadline' => 'date', 'event_date' => 'date',
            'submitted_on' => 'date', 'accepted_on' => 'date', 'presented_on' => 'date',
            'source_checked_at' => 'datetime',
        ];
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
