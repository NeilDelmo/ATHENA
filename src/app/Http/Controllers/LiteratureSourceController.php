<?php

namespace App\Http\Controllers;

use App\Actions\SaveLiteratureSource;
use App\Http\Requests\SearchSharedLiteratureRequest;
use App\Http\Requests\StoreLiteratureSourceRequest;
use App\Models\LiteratureSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class LiteratureSourceController extends Controller
{
    public function index(SearchSharedLiteratureRequest $request): JsonResponse
    {
        $query = Str::squish((string) $request->validated('query'));

        $sources = LiteratureSource::query()
            ->with(['addedBy:id,name', 'collections:id,name,slug'])
            ->when($query !== '', function ($builder) use ($query): void {
                $like = '%'.$query.'%';

                $builder->where(function ($sourceQuery) use ($like): void {
                    $sourceQuery
                        ->where('title', 'like', $like)
                        ->orWhere('authors', 'like', $like)
                        ->orWhere('venue', 'like', $like)
                        ->orWhere('doi', 'like', $like);
                });
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (LiteratureSource $source): array => $source->toLibraryArray())
            ->values();

        return response()->json(['sources' => $sources]);
    }

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
