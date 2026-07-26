<?php

use App\Models\AnnouncementImage;
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

    $this->actingAs($head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('Announcement')
        ->assertSee(route('announcement-images.index'), false);

    $this->actingAs($head)
        ->get(route('announcement-images.index'))
        ->assertOk()
        ->assertSee('Drag and drop announcement image')
        ->assertSee('data-announcement-image-form', false);

    $this->actingAs($head)
        ->post(route('announcement-images.store'), [
            'image' => UploadedFile::fake()->image('announcement.png'),
        ])
        ->assertRedirect(route('announcement-images.index'));

    $announcementImage = AnnouncementImage::query()->sole();

    expect($announcementImage->image_path)->toStartWith('announcements/');
    Storage::disk('local')->assertExists($announcementImage->image_path);

    $this->actingAs($head)
        ->get(route('announcement-images.index'))
        ->assertOk()
        ->assertSee('Uploaded announcements')
        ->assertSee(route('announcement-images.show', $announcementImage), false);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-research-call-carousel', false)
        ->assertSee(route('announcement-images.show', $announcementImage), false)
        ->assertDontSee('Submit a proposal');

    $this->actingAs($head)
        ->delete(route('announcement-images.destroy', $announcementImage))
        ->assertRedirect(route('announcement-images.index'));

    expect(AnnouncementImage::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($announcementImage->image_path);
});

test('faculty cannot access announcement image management', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('announcement-images.index'))
        ->assertForbidden();
});
