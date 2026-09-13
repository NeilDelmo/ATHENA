<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchJournalsRequest;
use App\Services\JournalRecommendationService;
use Illuminate\Http\JsonResponse;

class JournalSearchController extends Controller
{
    public function __invoke(SearchJournalsRequest $request, JournalRecommendationService $recommendations): JsonResponse
    {
        return response()->json($recommendations->recommend(
            query: $request->validated('query'),
            context: $request->validated('context'),
            openAccessOnly: $request->boolean('open_access'),
            recentYears: (int) ($request->validated('recent_years') ?? 10),
        ));
    }
}
