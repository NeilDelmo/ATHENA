<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportDraftEditStateRequest;
use App\Models\TopicProposal;
use App\Services\ProjectReportDraftState;
use Illuminate\Http\JsonResponse;

class ProjectReportDraftEditStateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReportDraftEditStateRequest $request, TopicProposal $topic, string $kind, string $reportingDate, int $source, ProjectReportDraftState $state): JsonResponse
    {
        return response()->json($state->forEditor($topic, $request->user(), $kind, $kind === 'terminal' ? null : $reportingDate, $source))
            ->header('Cache-Control', 'private, no-store');
    }
}
