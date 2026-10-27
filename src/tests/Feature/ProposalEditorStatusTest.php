<?php

use App\Models\ProposalDraft;
use App\Models\User;
use App\Services\ProposalPdfPreparation;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

test('PDF preparation stays out of the editor until the user needs to retry', function (string $status) {
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $user->id, 'project_title' => 'Quiet editor status']);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $this->mock(ProposalPdfPreparation::class)->shouldReceive('status')->once()->with($draft)->andReturn([
        'status' => $status, 'message' => 'Internal PDF progress', 'prepared' => 0, 'total' => 7,
    ]);

    $html = Blade::render('<x-proposal-pdf-preparation :proposal-draft="$draft" />', ['draft' => $draft]);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $panel = $xpath->query('//*[@data-proposal-pdf-preparation]')->item(0);

    expect($panel->hasAttribute('hidden'))->toBe($status !== 'failed')
        ->and($xpath->query('//*[@data-pdf-preparation-count]')->length)->toBe(0)
        ->and($panel->textContent)->not->toContain('0 / 7 PDFs')->not->toContain('Internal PDF progress')
        ->and(json_decode($panel->getAttribute('data-pdf-state'), true)['status'])->toBe($status);
    if ($status === 'failed') {
        expect($panel->textContent)->toContain('Your work is saved.')->toContain('Try again');
    }
})->with(['incomplete', 'needed', 'queued', 'preparing', 'retrying', 'stalled', 'ready', 'failed']);
