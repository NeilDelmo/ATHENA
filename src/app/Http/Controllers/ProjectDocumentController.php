<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Services\ImagePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function view(
        Request $request,
        TopicProposal $topic,
        ProjectDocument $projectDocument,
        ImagePreviewService $previews,
    ): StreamedResponse {
        Gate::forUser($request->user())->authorize('view', $topic);
        abort_unless(Storage::disk('local')->exists($projectDocument->file_path), 404);
        $mimeType = Storage::disk('local')->mimeType($projectDocument->file_path);
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        if ($request->boolean('preview') && str_starts_with($mimeType, 'image/')) {
            return $previews->response($projectDocument->file_path, $projectDocument->original_filename);
        }

        return Storage::disk('local')->response(
            $projectDocument->file_path,
            $projectDocument->original_filename,
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function download(
        Request $request,
        TopicProposal $topic,
        ProjectDocument $projectDocument,
    ): StreamedResponse {
        Gate::forUser($request->user())->authorize('view', $topic);
        abort_unless(Storage::disk('local')->exists($projectDocument->file_path), 404);
        $mimeType = Storage::disk('local')->mimeType($projectDocument->file_path);
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return Storage::disk('local')->download(
            $projectDocument->file_path,
            $projectDocument->original_filename,
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
