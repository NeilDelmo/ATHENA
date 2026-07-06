<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

final class ResearchCallWindow
{
    public static function acceptsSubmissions(
        string $status,
        DateTimeInterface $opensAt,
        DateTimeInterface $closesAt,
        ?DateTimeInterface $at = null,
    ): bool {
        $at ??= new DateTimeImmutable;

        return $status === 'open'
            && $at >= $opensAt
            && $at <= $closesAt;
    }
}
