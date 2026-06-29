<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Auth::user()->proposals()->latest()->get();

        return view('dashboard', compact('topics')); 
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'document' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ]);

        $path = $request->file('document')->store('proposals');

        if (! $path) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'The proposal document could not be uploaded. Please try again.']);
        }

        Auth::user()->proposals()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'initial_file_path' => $path,
            'status' => 'pending',
        ]);

        return redirect()->route('faculty.dashboard')->with('success', 'Proposal submitted successfully and sent to the Research Head.');
    }

    public function download(TopicProposal $topic)
    {
        $user = Auth::user();

        abort_unless($user->hasRole('research_head') || $topic->user_id === $user->id, 403);
        abort_unless(Storage::exists($topic->initial_file_path), 404);

        return Storage::download($topic->initial_file_path, basename($topic->initial_file_path));
    }
}
