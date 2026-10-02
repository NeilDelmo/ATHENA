<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchJournalsRequest;
use App\Models\TopicProposal;
use App\Services\JournalRecommendationService;
use Illuminate\Http\JsonResponse;

class JournalSearchController extends Controller
{
    /**
     * Bind the optional project so the form request can authorize project-specific searches.
     */
    public function __invoke(SearchJournalsRequest $request, JournalRecommendationService $recommendations, ?TopicProposal $topic = null): JsonResponse
    {
        return response()->json($recommendations->recommend(
            query: $request->validated('query') ?? '',
            context: $request->validated('context'),
            openAccessOnly: $request->boolean('open_access'),
            recentYears: (int) ($request->validated('recent_years') ?? 10),
            indexing: $request->validated('indexing') ?? 'prefer_scopus',
        ));
    }
}
