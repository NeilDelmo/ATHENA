<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalTemplate extends Model
{
    public const STAGE_INITIAL_SUBMISSION = 'initial_submission';

    public const STAGE_INITIAL_SCREENING = 'initial_screening';

    public const STAGE_REVISION_RESPONSE = 'revision_response';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'instructions',
        'revision_label',
        'workflow_stage',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'is_active',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, string> */
    public static function workflowStages(): array
    {
        return [
            self::STAGE_INITIAL_SUBMISSION => 'Initial faculty submission',
            self::STAGE_INITIAL_SCREENING => 'Initial Screening',
            self::STAGE_REVISION_RESPONSE => 'Revision / comment response',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
