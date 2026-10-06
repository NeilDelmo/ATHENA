<?php

use App\Models\ProposalDraft;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

test('application startup tolerates incomplete Reverb credentials and preserves configured broadcasters', function (array $overrides, string $expectedDriver, string $expectedClass) {
    $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        try {
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle(Illuminate\Http\Request::create('/up'));
            echo json_encode([
                'status' => $response->getStatusCode(),
                'driver' => config('broadcasting.default'),
                'broadcaster' => get_class(app(Illuminate\Contracts\Broadcasting\Factory::class)->connection()),
            ]);
        } catch (Throwable $exception) {
            echo json_encode(['error' => $exception->getMessage()]);
            exit(1);
        }
        PHP;
    $process = new Process([PHP_BINARY, '-r', $script], base_path(), array_replace([
        'APP_ENV' => 'testing', 'DB_DATABASE' => 'athena_testing',
        'BROADCAST_CONNECTION' => 'reverb',
        'REVERB_APP_ID' => 'broadcast-test-app',
        'REVERB_APP_KEY' => 'broadcast-test-key',
        'REVERB_APP_SECRET' => 'broadcast-test-secret',
    ], $overrides));
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['status' => 200, 'driver' => $expectedDriver, 'broadcaster' => $expectedClass]);
})->with([
    'missing application ID' => [['REVERB_APP_ID' => 'null'], 'null', NullBroadcaster::class],
    'missing application key' => [['REVERB_APP_KEY' => 'null'], 'null', NullBroadcaster::class],
    'missing application secret' => [['REVERB_APP_SECRET' => 'null'], 'null', NullBroadcaster::class],
    'empty application secret' => [['REVERB_APP_SECRET' => ''], 'null', NullBroadcaster::class],
    'configured Reverb' => [[], 'reverb', PusherBroadcaster::class],
    'explicitly disabled broadcasting' => [['BROADCAST_CONNECTION' => 'null'], 'null', NullBroadcaster::class],
]);

test('the embedded detailed proposal editor opens without realtime broadcasting', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = $faculty->proposals()->create([
        'title' => 'Embedded editor without Reverb', 'status' => 'revision_requested',
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id, 'topic_id' => $topic->id, 'project_title' => $topic->title,
        'project_leader' => $faculty->name,
    ]);
    config(['broadcasting.default' => 'null', 'broadcasting.connections.reverb.secret' => null]);
    app(Factory::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $this->actingAs($faculty)->get(route('faculty.proposal-drafts.detailed-proposal.edit', [$draft, 'revision_embed' => 1]))
        ->assertOk()
        ->assertSee('data-detailed-proposal-workspace', false)
        ->assertSee('revision-embedded', false)
        ->assertSee('Research alignment');
});
