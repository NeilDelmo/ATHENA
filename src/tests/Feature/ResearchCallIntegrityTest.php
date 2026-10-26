<?php

use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('research_head');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head);
    Storage::fake('local');
    Notification::fake();
    $this->payload = [
        'title' => 'Research call integrity',
        'academic_year' => '2026-2027',
        'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'closes_at' => now()->addMonth()->format('Y-m-d H:i:s'),
        'max_active_research_per_faculty' => 2,
        'status' => 'draft',
    ];
});

test('invalid research call milestones return validation errors', function (string $operation, string $prefix, string $invalidField) {
    $payload = [
        ...$this->payload,
        $prefix.'_start_date' => '2027-01-01',
        $prefix.'_end_date' => '2027-01-31',
        $prefix.'_'.$invalidField.'_date' => ['invalid'],
    ];

    if ($operation === 'create') {
        $response = $this->post(route('research-calls.store'), $payload);
    } else {
        $call = ResearchCall::create($this->payload);
        $response = $this->put(route('research-calls.update', $call), $payload);
        expect($call->fresh()->{$prefix.'_start_date'})->toBeNull();
    }

    $response->assertRedirect()->assertSessionHasErrors($prefix.'_'.$invalidField.'_date');
})->with(['create', 'update'])
    ->with(['initial_evaluation', 'paper_revisions', 'lrec', 'implementation'])
    ->with(['start', 'end']);

test('research call milestones still reject end dates before their start dates', function (string $operation, string $prefix) {
    $payload = [
        ...$this->payload,
        $prefix.'_start_date' => '2027-01-31',
        $prefix.'_end_date' => '2027-01-01',
    ];

    $response = $operation === 'create'
        ? $this->post(route('research-calls.store'), $payload)
        : $this->put(route('research-calls.update', ResearchCall::create($this->payload)), $payload);

    $response->assertRedirect()->assertSessionHasErrors($prefix.'_end_date');
})->with(['create', 'update'])
    ->with(['initial_evaluation', 'paper_revisions', 'lrec', 'implementation']);

test('failed research call creation removes the uploaded poster', function () {
    Exceptions::fake();
    Event::listen('eloquent.creating: '.ResearchCall::class, function (): void {
        throw new RuntimeException('Simulated research call save failure');
    });

    try {
        $this->postJson(route('research-calls.store'), [
            ...$this->payload,
            'reference_image' => UploadedFile::fake()->image('poster.png'),
        ])->assertInternalServerError();
    } finally {
        Event::forget('eloquent.creating: '.ResearchCall::class);
    }

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Simulated research call save failure');
    expect(ResearchCall::where('title', $this->payload['title'])->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles('research-calls'))->toBeEmpty();
    Notification::assertNothingSent();
});

test('a failed poster upload cannot save a research call or replace its existing poster', function (string $operation) {
    Exceptions::fake();
    $disk = Storage::disk('local');
    $disk->put('research-calls/original.png', 'original poster');
    $call = $operation === 'update'
        ? ResearchCall::create([...$this->payload, 'reference_image_path' => 'research-calls/original.png'])
        : null;
    $failingDisk = Mockery::mock(FilesystemAdapter::class);
    $failingDisk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->once()->andReturn($failingDisk);
    $payload = [
        ...$this->payload,
        'title' => 'Changed title',
        'reference_image' => UploadedFile::fake()->image('poster.png'),
    ];

    $response = $operation === 'create'
        ? $this->postJson(route('research-calls.store'), $payload)
        : $this->putJson(route('research-calls.update', $call), $payload);

    $response->assertInternalServerError();
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'The research-call poster could not be stored.');
    expect(ResearchCall::where('title', 'Changed title')->exists())->toBeFalse();
    if ($call !== null) {
        expect($call->fresh()->reference_image_path)->toBe('research-calls/original.png');
    }
    $disk->assertExists('research-calls/original.png');
    Notification::assertNothingSent();
})->with(['create', 'update']);
