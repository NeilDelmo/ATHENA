<?php

namespace App\Models;

use Database\Factories\ResearchOutcomeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchOutcome extends Model
{
    /** @use HasFactory<ResearchOutcomeFactory> */
    use HasFactory;

    public const LABELS = [
        'publication' => 'Publication',
        'presentation' => 'Presentation',
        'patent' => 'Patent',
        'commercialized' => 'Commercialized',
        'utilization' => 'Utilization',
    ];

    public const FUNDING_SOURCES = [
        'school' => 'School-funded',
        'external' => 'Externally funded',
        'unfunded' => 'Non-funded',
    ];

    protected $fillable = ['topic_id', 'leader_id', 'title', 'team_members', 'funding_source', 'labels', 'lock_version'];

    protected $attributes = ['labels' => '[]', 'lock_version' => 0];

    protected function casts(): array
    {
        return ['labels' => 'array', 'lock_version' => 'integer'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function projectTitle(): string
    {
        return $this->topic?->title ?? $this->title;
    }

    public function projectLeader(): User
    {
        return $this->topic?->user ?? $this->leader;
    }

    public function projectCollege(): ?string
    {
        return $this->topic_id ? $this->topic->projectCollege() : $this->leader->college;
    }

    public function detailUrl(): string
    {
        return $this->topic_id
            ? route('research-outcomes.projects.show', $this->topic_id)
            : route('research-outcomes.show', $this);
    }
}
