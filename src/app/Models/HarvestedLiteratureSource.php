<?php

namespace App\Models;

use Database\Factories\HarvestedLiteratureSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HarvestedLiteratureSource extends Model
{
    /** @use HasFactory<HarvestedLiteratureSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'repository_key', 'url_hash', 'url', 'fingerprint', 'title', 'authors',
        'abstract', 'publication_year', 'doi', 'metadata', 'status', 'failure_reason',
        'queued_at', 'last_attempted_at', 'harvested_at', 'next_harvest_at',
    ];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'publication_year' => 'integer',
            'queued_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'harvested_at' => 'datetime',
            'next_harvest_at' => 'datetime',
        ];
    }
}
