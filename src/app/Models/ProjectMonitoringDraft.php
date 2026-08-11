<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMonitoringDraft extends Model
{
    protected $fillable = [
        'topic_id',
        'user_id',
        'source_report_id',
        'source_key',
        'source_data',
        'lock_version',
    ];

    protected $attributes = [
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'source_data' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sourceReport(): BelongsTo
    {
        return $this->belongsTo(ProjectProgressReport::class, 'source_report_id');
    }

    public function scopeForSource(Builder $query, ?ProjectProgressReport $sourceReport): Builder
    {
        return $query->where('source_key', self::sourceKey($sourceReport));
    }

    public static function sourceKey(?ProjectProgressReport $sourceReport): string
    {
        return $sourceReport === null ? 'new' : 'revision:'.$sourceReport->id;
    }
}
