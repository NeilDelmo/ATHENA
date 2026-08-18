<?php

namespace App\Models;

use Database\Factories\ResearchAssistantConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAssistantConversation extends Model
{
    /** @use HasFactory<ResearchAssistantConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'messages',
        'context',
        'summary',
        'summarized_message_count',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'context' => 'array',
            'summarized_message_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
