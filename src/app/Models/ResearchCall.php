<?php

namespace App\Models;

use App\Support\ResearchCallWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchCall extends Model
{
    public const MAXIMUM_BUDGET = 150000;

    protected $attributes = [
        'maximum_budget' => self::MAXIMUM_BUDGET,
    ];

    protected $fillable = [
        'title', 'academic_year', 'term', 'description', 'opens_at', 'closes_at',
        'reference_image_path',
        'initial_evaluation_start_date', 'initial_evaluation_end_date',
        'paper_revisions_start_date', 'paper_revisions_end_date',
        'lrec_start_date', 'lrec_end_date',
        'implementation_start_date', 'implementation_end_date',
        'max_active_research_per_faculty', 'maximum_budget', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'initial_evaluation_start_date' => 'date',
            'initial_evaluation_end_date' => 'date',
            'paper_revisions_start_date' => 'date',
            'paper_revisions_end_date' => 'date',
            'lrec_start_date' => 'date',
            'lrec_end_date' => 'date',
            'implementation_start_date' => 'date',
            'implementation_end_date' => 'date',
            'maximum_budget' => 'decimal:2',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ResearchCategory::class, 'research_call_category');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(TopicProposal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAcceptingSubmissions(Builder $query): Builder
    {
        $at = now();

        return $query
            ->where('status', 'open')
            ->where('opens_at', '<=', $at)
            ->where('closes_at', '>=', $at);
    }

    public function isAcceptingSubmissions(): bool
    {
        return ResearchCallWindow::acceptsSubmissions(
            $this->status,
            $this->opens_at,
            $this->closes_at,
        );
    }

    public function lifecycleStatus(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if ($this->status === 'closed') {
            return 'closed';
        }

        if (now()->lt($this->opens_at)) {
            return 'scheduled';
        }

        if (now()->gt($this->closes_at)) {
            return 'ended';
        }

        return 'open';
    }

    public function budgetCeiling(): float
    {
        return self::MAXIMUM_BUDGET;
    }
}
