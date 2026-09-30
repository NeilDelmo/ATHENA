<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchLiteratureRequest;
use App\Services\LiteratureSearchService;
use Illuminate\Http\JsonResponse;

class LiteratureSearchController extends Controller
{
    public function __invoke(SearchLiteratureRequest $request, LiteratureSearchService $literatureSearch): JsonResponse
    {
        $validated = $request->validated();
        $payload = $literatureSearch->search($validated['query'], [
            'year_from' => $validated['year_from'] ?? null,
            'year_to' => $validated['year_to'] ?? null,
            'min_citations' => $validated['min_citations'] ?? null,
            'open_access' => $validated['open_access'] ?? false,
        ], $validated['context'] ?? '');

        if ($literatureSearch->allProvidersFailed() && $payload['results'] === []) {
            return response()->json([
                'message' => 'The literature search providers could not be reached right now. Please try again in a moment.',
                'results' => [],
                'failed_sources' => $payload['failed_sources'],
            ], 503);
        }

        return response()->json($payload);
    }
}
