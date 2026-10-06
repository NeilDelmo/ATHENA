<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupLiteratureMetadataRequest;
use App\Services\LiteratureMetadataService;
use Illuminate\Http\JsonResponse;

class LiteratureMetadataController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LookupLiteratureMetadataRequest $request, LiteratureMetadataService $metadata): JsonResponse
    {
        return response()->json($metadata->lookup($request->validated('identifier')));
    }
}
