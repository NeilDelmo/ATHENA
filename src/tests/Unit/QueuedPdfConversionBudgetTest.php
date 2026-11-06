<?php

use App\Jobs\PrepareProposalDraftPdfs;
use App\Services\ProposalPdfPreparation;
use Tests\TestCase;

uses(TestCase::class);

test('queued PDF preparation preserves time for both conversion phases and restores the request allowance', function (int $configured, int $expected, bool $fails, bool $delegate = false) {
    config(['document_pdf.timeout_seconds' => $configured, 'document_pdf.delegate_to_php_cli' => $delegate]);
    $preparation = Mockery::mock(ProposalPdfPreparation::class);
    $preparation->shouldReceive('run')->once()->with(7, 'budget-test')->andReturnUsing(function () use ($expected, $fails): void {
        expect(config('document_pdf.timeout_seconds'))->toBe($expected);
        expect(config('document_pdf.delegate_to_php_cli'))->toBeFalse();
        if ($fails) {
            throw new RuntimeException('Conversion failed.');
        }
    });
    $job = new PrepareProposalDraftPdfs(7, 'budget-test');
    if ($fails) {
        expect(fn () => $job->handle($preparation))->toThrow(RuntimeException::class, 'Conversion failed.');
    } else {
        $job->handle($preparation);
    }
    expect(config('document_pdf.timeout_seconds'))->toBe($configured);
    expect(config('document_pdf.delegate_to_php_cli'))->toBe($delegate);
})->with([
    'large report allowance' => [240, 120, false],
    'shorter configured allowance' => [60, 60, false],
    'failed preparation' => [240, 120, true, true],
    'already running in the queue CLI' => [240, 120, false, true],
]);
