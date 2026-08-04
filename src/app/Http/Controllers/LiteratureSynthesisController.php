<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureSynthesisException;
use App\Http\Requests\SynthesizeLiteratureRequest;
use App\Services\LiteratureSynthesisService;
use Illuminate\Http\JsonResponse;

class LiteratureSynthesisController extends Controller
{
    public function __invoke(
        SynthesizeLiteratureRequest $request,
        LiteratureSynthesisService $literatureSynthesis,
    ): JsonResponse {
        try {
            $payload = $literatureSynthesis->synthesize($request->validated());
        } catch (LiteratureSynthesisException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json($payload);
    }
}
