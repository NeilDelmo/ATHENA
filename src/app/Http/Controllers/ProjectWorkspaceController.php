<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProjectWorkspaceRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectDocumentLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectWorkspaceController extends Controller
{
    public function documents(Request $request, TopicProposal $topic, ProjectDocumentLibrary $library): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $topic);
        abort_unless($topic->hasIssuedNoticeToProceed(), 404);

        return response()->json($library->build($topic, $request->user()));
    }

    public function update(UpdateProjectWorkspaceRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            $current = $user->projectWorkspacePreferences();
            $workspace = $request->validated();

            if ((int) $workspace['revision'] !== $current['revision']) {
                return response()->json(['message' => 'Your workspace changed in another tab. Reload the page before saving again.'], 409);
            }

            $workspace['revision'] = $current['revision'] + 1;
            $workspace['assignments'] = array_map(fn (array $assignment): array => [
                ...$assignment, 'project_id' => (int) $assignment['project_id'],
            ], $workspace['assignments']);
            $user->forceFill(['project_workspace' => $workspace])->save();

            return response()->json(['workspace' => $workspace]);
        });
    }
}
