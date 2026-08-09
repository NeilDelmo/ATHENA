<?php

use App\Models\AnnouncementImage;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('research heads can manage announcement images and announcements appear in the faculty carousel', function () {
    $this->withoutVite();
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $researchCall = ResearchCall::create([
        'title' => 'Linked Announcement Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);

    $this->actingAs($head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('Announcement')
        ->assertSee(route('announcement-images.index'), false);

    $this->actingAs($head)
        ->get(route('announcement-images.index'))
        ->assertOk()
        ->assertSee('Drop or paste your announcement')
        ->assertSee('Publish with the right audience.')
        ->assertSee('General announcement &mdash; show until archived', false)
        ->assertSee('rounded-3xl border border-gray-200 bg-white px-6 py-7', false)
        ->assertSee('rounded-2xl border border-gray-200 bg-white p-5 text-gray-950', false)
        ->assertSee($researchCall->title)
        ->assertSee('data-announcement-image-form', false);

    $this->actingAs($head)
        ->post(route('announcement-images.store'), [
            'image' => UploadedFile::fake()->image('announcement.png'),
            'research_call_id' => $researchCall->id,
        ])
        ->assertRedirect(route('announcement-images.index'));

    $announcementImage = AnnouncementImage::query()->sole();

    expect($announcementImage->image_path)->toStartWith('announcements/')
        ->and($announcementImage->research_call_id)->toBe($researchCall->id);
    Storage::disk('local')->assertExists($announcementImage->image_path);

    $this->actingAs($head)
        ->get(route('announcement-images.index'))
        ->assertOk()
        ->assertSee('Active announcements')
        ->assertSee(route('announcement-images.show', $announcementImage), false);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-carousel', false)
        ->assertSee(route('announcement-images.show', $announcementImage), false)
        ->assertSee('Start a proposal');

    $researchCall->update(['status' => 'closed']);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee(route('announcement-images.show', $announcementImage), false)
        ->assertSee('No open call');

    $this->actingAs($head)
        ->patch(route('announcement-images.update', $announcementImage), [
            'research_call_id' => null,
        ])
        ->assertRedirect(route('announcement-images.index'));

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee(route('announcement-images.show', $announcementImage), false);

    $this->actingAs($head)
        ->patch(route('announcement-images.archive', $announcementImage))
        ->assertRedirect(route('announcement-images.index'))
        ->assertSessionHas('success', 'Announcement archived. Faculty can no longer see it.');

    expect($announcementImage->fresh()->archived_at)->not->toBeNull();
    Storage::disk('local')->assertExists($announcementImage->image_path);

    $this->actingAs($head)
        ->get(route('announcement-images.index'))
        ->assertOk()
        ->assertSee('Archived announcements')
        ->assertSee('Restore announcement');

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertDontSee(route('announcement-images.show', $announcementImage), false);

    $this->actingAs($faculty)
        ->get(route('announcement-images.show', $announcementImage))
        ->assertNotFound();

    $this->actingAs($head)
        ->patch(route('announcement-images.restore', $announcementImage))
        ->assertRedirect(route('announcement-images.index'))
        ->assertSessionHas('success', 'Announcement restored.');

    expect($announcementImage->fresh()->archived_at)->toBeNull();

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee(route('announcement-images.show', $announcementImage), false);
});

test('faculty cannot access announcement image management', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('announcement-images.index'))
        ->assertForbidden();
});
