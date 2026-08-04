<?php

namespace App\Http\Controllers;

use App\Actions\SaveLiteratureSource;
use App\Http\Requests\StoreLiteratureSourceRequest;
use Illuminate\Http\JsonResponse;

class LiteratureSourceController extends Controller
{
    public function store(StoreLiteratureSourceRequest $request, SaveLiteratureSource $saveLiteratureSource): JsonResponse
    {
        $result = $saveLiteratureSource->handle($request->validated(), $request->user());

        return response()->json([
            'message' => $result['already_saved']
                ? 'This paper is already in the shared library; its metadata and collections were updated.'
                : 'Paper saved to the shared literature library.',
            'already_saved' => $result['already_saved'],
            'source' => $result['source']->toLibraryArray(),
        ], $result['already_saved'] ? 200 : 201);
    }
}
