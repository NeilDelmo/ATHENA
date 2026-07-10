<?php

namespace App\Http\Controllers;

use App\Services\ConferenceScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConferenceSearchController extends Controller
{
    public function __invoke(Request $request, ConferenceScraperService $conferenceScraper): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'min:3', 'max:140'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $conferenceScraper->search($validator->validated()['query']);

        if ($conferenceScraper->allSourcesFailed()) {
            return response()->json([
                'message' => 'The conference listing source could not be scraped right now. Please try again in a moment.',
                'results' => [],
                'failed_sources' => $payload['failed_sources'],
            ], 503);
        }

        return response()->json($payload);
    }
}
