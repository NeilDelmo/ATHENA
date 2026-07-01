<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchCategory extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function researchCalls(): BelongsToMany
    {
        return $this->belongsToMany(ResearchCall::class, 'research_call_category');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(TopicProposal::class);
    }
}
