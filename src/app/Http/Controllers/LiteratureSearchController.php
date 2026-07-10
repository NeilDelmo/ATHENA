<?php

namespace App\Http\Controllers;

use App\Services\LiteratureSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LiteratureSearchController extends Controller
{
    public function __invoke(Request $request, LiteratureSearchService $literatureSearch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'min:3', 'max:180'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'min_citations' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'open_access' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $yearFrom = $request->integer('year_from') ?: null;
            $yearTo = $request->integer('year_to') ?: null;

            if ($yearFrom && $yearTo && $yearFrom > $yearTo) {
                $validator->errors()->add('year_from', 'The starting year must be before or equal to the ending year.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $payload = $literatureSearch->search($validated['query'], [
            'year_from' => $validated['year_from'] ?? null,
            'year_to' => $validated['year_to'] ?? null,
            'min_citations' => $validated['min_citations'] ?? null,
            'open_access' => $validated['open_access'] ?? false,
        ]);

        if ($literatureSearch->allProvidersFailed()) {
            return response()->json([
                'message' => 'The literature search providers could not be reached right now. Please try again in a moment.',
                'results' => [],
                'failed_sources' => $payload['failed_sources'],
            ], 503);
        }

        return response()->json($payload);
    }
}
