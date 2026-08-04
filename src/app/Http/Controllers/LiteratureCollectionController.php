<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLiteratureCollectionRequest;
use App\Models\LiteratureCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class LiteratureCollectionController extends Controller
{
    public function store(StoreLiteratureCollectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = Str::squish($validated['name']);
        $slug = Str::slug($name) ?: hash('sha256', Str::lower($name));
        $collection = LiteratureCollection::firstOrCreate(
            ['slug' => $slug],
            [
                'created_by' => $request->user()->getKey(),
                'name' => $name,
                'description' => Str::squish((string) ($validated['description'] ?? '')) ?: null,
            ],
        );

        return response()->json([
            'message' => $collection->wasRecentlyCreated
                ? 'Shared collection created.'
                : 'That shared collection already exists.',
            'already_exists' => ! $collection->wasRecentlyCreated,
            'collection' => [
                'id' => $collection->getKey(),
                'name' => $collection->name,
                'slug' => $collection->slug,
                'sources_count' => 0,
            ],
        ], $collection->wasRecentlyCreated ? 201 : 200);
    }
}
