<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchKnowledgeEntry extends Model
{
    protected $fillable = [
        'title',
        'category',
        'content',
        'source_url',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public static function categoryOptions(): array
    {
        return [
            'institutional_policy' => 'Institutional policy',
            'research_process' => 'Research process',
            'ethics' => 'Ethics and compliance',
            'proposal_writing' => 'Proposal writing',
            'funding' => 'Funding and budget',
            'methodology' => 'Methodology guidance',
            'general' => 'General guidance',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? str($this->category)->replace('_', ' ')->title()->toString();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
