<?php

use App\Services\ResearchAssistantPassageRetriever;

test('large documents return the expected excerpts within a bounded ranking heap', function (string $question, array $expectedIds) {
    $passages = [];
    for ($number = 1; $number <= 1000; $number++) {
        $terms = array_map(fn (int $column): string => 'token'.$number.'x'.$column, range(1, 60));
        $passages[] = [
            'id' => (string) $number,
            'location' => 'Source paragraph '.$number,
            'text' => implode(' ', $terms),
            ...($number === 812 ? ['title' => 'Zinc spectrometer calibration protocol'] : []),
        ];
    }

    memory_reset_peak_usage();
    $initialMemory = memory_get_usage();
    $result = (new ResearchAssistantPassageRetriever)->select($passages, $question, [], 8000, 4);
    $peakIncrease = memory_get_peak_usage() - $initialMemory;

    expect($result['total_passages'])->toBe(1000)
        ->and(array_column($result['passages'], 'id'))->toBe($expectedIds)
        ->and($peakIncrease)->toBeLessThan(4 * 1024 * 1024);
})->with([
    'overview' => ['Summarize this document.', ['1', '334', '667', '1000']],
    'ranked question' => ['What does the zinc spectrometer calibration protocol require?', ['811', '812', '813']],
]);
