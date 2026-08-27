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

        $deadlineNotice->dismissForToday($request->user(), $researchCall);

        return response()->json(['dismissed' => true]);
    }
}
