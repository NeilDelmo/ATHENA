<?php

namespace App\Http\Controllers;

use App\Models\ResearchCall;
use App\Support\ResearchCallDeadlineNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchCallDeadlineDismissalController extends Controller
{
    public function store(
        Request $request,
        ResearchCall $researchCall,
        ResearchCallDeadlineNotice $deadlineNotice,
    ): JsonResponse {
        abort_unless($deadlineNotice->canBeDismissedBy($request->user(), $researchCall), 404);

        $validated = $request->validate(['deadline_at' => ['required', 'string', 'max:35']]);
        if ($validated['deadline_at'] !== $researchCall->closes_at->toIso8601String()) {
            return response()->json(['message' => 'The submission deadline changed. Reload the page to see the updated reminder.'], 409);
        }

        $deadlineNotice->dismissForToday($request->user(), $researchCall);

        return response()->json(['dismissed' => true]);
    }
}
