<?php

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\User;
use App\Notifications\ResearchCallUpdatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->otherUser = User::factory()->create();
    $this->category = ResearchCategory::create(['name' => 'Environment']);
    $this->call = ResearchCall::create([
        'title' => 'Original Research Call',
        'academic_year' => '2026-2027',
        'term' => 'First Semester',
        'description' => 'Original guidelines.',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
        'reference_image_path' => 'research-calls/original.jpg',
    ]);
    $this->call->categories()->attach($this->category);
    Storage::fake('local');
    Storage::disk('local')->put('research-calls/original.jpg', 'original poster');
});

test('Research Head can edit a research call and replace its poster', function () {
    config(['queue.default' => 'database']);

    $newPoster = UploadedFile::fake()->image('updated-poster.png');

    $this->actingAs($this->head)
        ->from(route('research-calls.index'))
        ->put(route('research-calls.update', $this->call), [
            'title' => 'Updated Research Call',
            'academic_year' => '2027-2028',
            'term' => 'Second Semester',
            'description' => 'Updated guidelines.',
            'reference_image' => $newPoster,
            'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            'initial_evaluation_start_date' => '2027-03-01',
            'initial_evaluation_end_date' => '2027-03-10',
            'paper_revisions_start_date' => '2027-03-11',
            'paper_revisions_end_date' => '2027-03-20',
            'lrec_start_date' => '2027-04-01',
            'lrec_end_date' => '2027-04-10',
            'implementation_start_date' => '2027-08-01',
            'implementation_end_date' => '2028-01-31',
            'max_active_research_per_faculty' => 3,
            'maximum_budget' => 125000,
            'categories' => 'Technology, Environment',
        ])
        ->assertRedirect(route('research-calls.index'));

    $this->call->refresh();

    expect($this->call->title)->toBe('Updated Research Call')
        ->and($this->call->academic_year)->toBe('2027-2028')
        ->and($this->call->description)->toBe('Updated guidelines.')
        ->and($this->call->max_active_research_per_faculty)->toBe(3)
        ->and((float) $this->call->maximum_budget)->toBe(125000.0)
        ->and($this->call->categories()->pluck('name')->all())->toEqual(['Environment', 'Technology'])
        ->and($this->call->reference_image_path)->not->toBe('research-calls/original.jpg');

    Storage::disk('local')->assertExists($this->call->reference_image_path);
    Storage::disk('local')->assertMissing('research-calls/original.jpg');

    foreach ([$this->head, $this->faculty, $this->otherUser] as $user) {
        $notification = $user->notifications()->firstOrFail();

        expect($notification->type)->toBe(ResearchCallUpdatedNotification::class)
            ->and($notification->data)->toMatchArray([
                'title' => 'Research call updated',
                'research_call_id' => $this->call->id,
                'url' => route('research-calls.index'),
            ]);
    }
});

test('Research Head sees edit controls for research calls', function () {
    $this->actingAs($this->head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('Edit research call')
        ->assertSee('Save changes')
        ->assertSee('Update poster image')
        ->assertSee(route('research-calls.update', $this->call), false);
});

test('other workspaces cannot edit a research call', function () {
    $this->actingAs($this->faculty)
        ->put(route('research-calls.update', $this->call), [])
        ->assertForbidden();
});
