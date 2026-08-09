<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementImage extends Model
{
    protected $fillable = ['image_path', 'research_call_id', 'archived_at'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeVisibleToFaculty(Builder $query): Builder
    {
        return $query->active()->where(function (Builder $query): void {
            $query->whereNull('research_call_id')
                ->orWhereHas('researchCall', fn (Builder $researchCallQuery) => $researchCallQuery->acceptingSubmissions());
        });
    }

    public function isVisibleToFaculty(): bool
    {
        return $this->archived_at === null
            && ($this->research_call_id === null || $this->researchCall?->isAcceptingSubmissions() === true);
    }
}
