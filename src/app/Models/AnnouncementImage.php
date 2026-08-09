<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementImage extends Model
{
    protected $fillable = ['image_path', 'research_call_id'];

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }
}
