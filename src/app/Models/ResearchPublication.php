<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ResearchPublication extends Model
{
    use HasFactory;

    protected $attributes = ['source' => 'Researcher entry'];

    protected $fillable = [
        'user_id', 'fingerprint', 'openalex_id', 'doi', 'title', 'authors', 'venue',
        'year', 'type', 'url', 'source', 'source_checked_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'source_checked_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public static function normalizeDoi(?string $doi): ?string
    {
        $doi = Str::lower(trim((string) $doi));
        $doi = preg_replace('~^(?:https?://(?:dx\\.)?doi\\.org/|doi:\\s*)~i', '', $doi);

        return $doi !== '' ? $doi : null;
    }

    public static function fingerprint(array $data): string
    {
        return hash('sha256', self::normalizeDoi($data['doi'] ?? null)
            ?? ($data['openalex_id'] ?? Str::lower(Str::squish($data['title']))));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(TopicProposal::class, 'research_publication_topic', 'research_publication_id', 'topic_id')->withTimestamps();
    }
}
