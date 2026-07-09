<?php

use App\Support\ResearchCallWindow;

test('an open research call accepts submissions during its configured period', function () {
    $accepts = ResearchCallWindow::acceptsSubmissions(
        'open',
        new \DateTimeImmutable('2026-06-01 00:00:00'),
        new \DateTimeImmutable('2026-07-31 23:59:59'),
        new \DateTimeImmutable('2026-07-01 12:00:00'),
    );

    expect($accepts)->toBeTrue();
});

test('a call rejects submissions when it is draft closed expired or not yet open', function (
    string $status,
    string $opensAt,
    string $closesAt,
) {
    $accepts = ResearchCallWindow::acceptsSubmissions(
        $status,
        new \DateTimeImmutable($opensAt),
        new \DateTimeImmutable($closesAt),
        new \DateTimeImmutable('2026-07-01 12:00:00'),
    );

    expect($accepts)->toBeFalse();
})->with([
    'draft' => ['draft', '2026-06-01 00:00:00', '2026-07-31 23:59:59'],
    'closed' => ['closed', '2026-06-01 00:00:00', '2026-07-31 23:59:59'],
    'expired' => ['open', '2026-06-01 00:00:00', '2026-06-30 23:59:59'],
    'not yet open' => ['open', '2026-07-02 00:00:00', '2026-07-31 23:59:59'],
]);
