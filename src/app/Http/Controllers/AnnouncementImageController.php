<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementImageRequest;
use App\Models\AnnouncementImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementImageController extends Controller
{
    public function index(): View
    {
        return view('announcement_images.index', [
            'announcementImages' => AnnouncementImage::query()->latest()->get(),
        ]);
    }

    public function store(StoreAnnouncementImageRequest $request): RedirectResponse
    {
        AnnouncementImage::create([
            'image_path' => $request->file('image')->store('announcements', 'local'),
        ]);

        return redirect()->route('announcement-images.index')->with('success', 'Announcement image uploaded successfully.');
    }

    public function show(AnnouncementImage $announcementImage): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($announcementImage->image_path), 404);

        return Storage::disk('local')->response(
            $announcementImage->image_path,
            basename($announcementImage->image_path),
            [
                'Content-Type' => Storage::disk('local')->mimeType($announcementImage->image_path),
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(AnnouncementImage $announcementImage): RedirectResponse
    {
        Storage::disk('local')->delete($announcementImage->image_path);
        $announcementImage->delete();

        return redirect()->route('announcement-images.index')->with('success', 'Announcement image removed.');
    }
}
