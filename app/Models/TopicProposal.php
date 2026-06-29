<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicProposal extends Model
{
    protected $table = 'topics';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'initial_file_path',
        'final_file_path',
        'status', // 'pending', 'approved', 'rejected'
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
