<?php

namespace App\Http\Controllers;

use App\Actions\StoreProjectDocuments;
use App\Http\Requests\StoreProjectDocumentRequest;
use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function store(
        StoreProjectDocumentRequest $request,
        TopicProposal $topic,
        StoreProjectDocuments $storeProjectDocuments,
    ): RedirectResponse {
        $documents = $storeProjectDocuments->handle(
            $topic,
            $request->user(),
            $request->file('documents', []),
            $request->string('category')->toString(),
            $request->string('note')->trim()->toString(),
        );

        return redirect()
            ->to(route('topics.show', $topic).'#project-files')
            ->with('project_documents_open', true)
            ->with('success', $documents->count().' '.str('PDF')->plural($documents->count()).' added to project files.');
    }

    public function view(
        Request $request,
        TopicProposal $topic,
        ProjectDocument $projectDocument,
    ): StreamedResponse {
        Gate::forUser($request->user())->authorize('view', $topic);
        abort_unless(Storage::disk('local')->exists($projectDocument->file_path), 404);

        return Storage::disk('local')->response(
            $projectDocument->file_path,
            $projectDocument->original_filename,
            [
                'Content-Type' => 'application/pdf',
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

        return Storage::disk('local')->download(
            $projectDocument->file_path,
            $projectDocument->original_filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
