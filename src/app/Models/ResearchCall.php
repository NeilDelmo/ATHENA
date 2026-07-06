<?php

namespace App\Models;

use App\Support\ResearchCallWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchCall extends Model
{
    public const MAXIMUM_BUDGET = 150000;

    protected $fillable = [
        'title', 'academic_year', 'term', 'description', 'opens_at', 'closes_at',
        'max_active_research_per_faculty', 'maximum_budget', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
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
        $configuredBudget = $this->maximum_budget === null
            ? self::MAXIMUM_BUDGET
            : (float) $this->maximum_budget;

        return min($configuredBudget, self::MAXIMUM_BUDGET);
    }
}
