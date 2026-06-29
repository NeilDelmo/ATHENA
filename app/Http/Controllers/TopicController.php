<?php

namespace App\Http\Controllers;
use App\Models\TopicProposal
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class TopicController extends Controller
{
   public function index()
    {
        // Fetch only the logged-in user's submitted topics
        $topics = Auth::user()->proposals()->latest()->get();

        return view('dashboard', compact('topics')); 
    }
    public function store(Request $request)
    {
        // 1. Validate incoming data (including the document)
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document' => 'required|file|mimes:pdf,doc,docx|max:10240', // Max 10MB PDF/Word docs
        ]);

        // 2. Handle the file upload securely
        if ($request->hasFile('document')) {
            // Stores it in storage/app/private/proposals or storage/app/public/proposals
            $path = $request->file('document')->store('proposals');
            $validated['initial_file_path'] = $path;
        }

        // 3. Save the proposal via the user's relationship
        Auth::user()->proposals()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'initial_file_path' => $validated['initial_file_path'],
            'status' => 'pending', // Explicitly setting it, though migration handles default
        ]);

        // 4. Kick back to the dashboard with a success session banner
        return redirect()->route('faculty.dashboard')->with('success', 'Proposal submitted successfully!');
    }
}
