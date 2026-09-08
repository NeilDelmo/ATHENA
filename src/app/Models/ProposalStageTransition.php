<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalStageTransition extends Model
{
    protected $fillable = ['from_status', 'to_status', 'previous_started_at', 'changed_at'];

    protected function casts(): array
    {
        return ['previous_started_at' => 'datetime', 'changed_at' => 'datetime'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }
}
