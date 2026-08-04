<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LiteratureCollection extends Model
{
    protected $fillable = [
        'created_by',
        'name',
        'slug',
        'description',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(LiteratureSource::class, 'literature_collection_source')
            ->withPivot('added_by')
            ->withTimestamps();
    }
}
