<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchCallDeadlineDismissal extends Model
{
    protected $fillable = [
        'user_id',
        'research_call_id',
        'dismissed_on',
        'deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_on' => 'date',
            'deadline_at' => 'datetime',
        ];
    }

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
