<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementImageRequest;
use App\Http\Requests\UpdateAnnouncementImageRequest;
use App\Models\AnnouncementImage;
use App\Models\ResearchCall;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementImageController extends Controller
{
    public function index(): View
    {
        return view('announcement_images.index', [
            'announcementImages' => AnnouncementImage::query()->active()->with('researchCall')->latest()->get(),
            'archivedAnnouncementImages' => AnnouncementImage::query()->archived()->with('researchCall')->latest('archived_at')->get(),
            'researchCalls' => ResearchCall::query()->latest('opens_at')->get(),
        ]);
    }

    public function store(StoreAnnouncementImageRequest $request): RedirectResponse
    {
        AnnouncementImage::create([
            'image_path' => $request->file('image')->store('announcements', 'local'),
            'research_call_id' => $request->validated('research_call_id'),
        ]);

        return redirect()->route('announcement-images.index')->with('success', 'Announcement image uploaded successfully.');
    }

    public function update(
        UpdateAnnouncementImageRequest $request,
        AnnouncementImage $announcementImage,
    ): RedirectResponse {
        $announcementImage->update($request->validated());

        return redirect()
            ->route('announcement-images.index')
            ->with('success', 'Announcement visibility updated.');
    }

    public function show(Request $request, AnnouncementImage $announcementImage): StreamedResponse
    {
        abort_unless(
            $request->user()->isUsingWorkspace('research_head') || $announcementImage->isVisibleToFaculty(),
            404,
        );
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

    public function archive(AnnouncementImage $announcementImage): RedirectResponse
    {
        $announcementImage->update(['archived_at' => now()]);

        return redirect()->route('announcement-images.index')->with('success', 'Announcement archived. Faculty can no longer see it.');
    }

    public function restore(AnnouncementImage $announcementImage): RedirectResponse
    {
        $announcementImage->update(['archived_at' => null]);

        return redirect()->route('announcement-images.index')->with('success', 'Announcement restored.');
    }
}
