<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use App\Services\TopicLiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TopicLiveStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, TopicProposal $topic, TopicLiveStatus $status): JsonResponse
    {
        Gate::authorize('view', $topic);

        return response()->json($status->forTopic($topic, $request->user()))
            ->header('Cache-Control', 'private, no-store');
    }
}
