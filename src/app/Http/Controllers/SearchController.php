<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\AccessibleRecordSearch;
use Illuminate\Http\Response;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SearchRequest $request, AccessibleRecordSearch $search): Response
    {
        $query = trim($request->validated('q') ?? '');
        $category = $request->validated('category') ?? 'all';

        return response()->view('search.index', [
            'query' => $query,
            'category' => $category,
            'categories' => AccessibleRecordSearch::CATEGORIES,
            'results' => $query === '' ? null : $search->search($request->user(), $query, $category)->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
