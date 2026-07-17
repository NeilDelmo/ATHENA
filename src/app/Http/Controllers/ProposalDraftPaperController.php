<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\UpdateProposalDraftPaperRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalTemplate;
use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProposalDraftPaperController extends Controller
{
    public function edit(
        ProposalDraft $proposalDraft,
        string $paper,
        ProposalPaperCatalog $catalog,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $paper = $this->uploadPaper($catalog, $paper);
        $documents = $this->documentsFor($proposalDraft, $paper)->get();
        $template = $this->activeTemplate($paper);

        return view('faculty.proposal-drafts.papers.edit', compact(
            'proposalDraft',
            'paper',
            'documents',
            'template',
        ));
    }

    public function update(
        UpdateProposalDraftPaperRequest $request,
        ProposalDraft $proposalDraft,
        string $paper,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $this->uploadPaper($catalog, $paper);
        $storedFiles = $this->storeUploadedFiles(
            $request->file('documents', []),
            $proposalDraft,
            $paper,
        );

        $paper['multiple']
            ? $this->appendDocuments($proposalDraft, $paper, $storedFiles)
            : $this->replaceDocument(
                $proposalDraft,
                $paper,
                $storedFiles[0],
                $request->integer('document_version'),
                $saveProposalDraftDocument,
            );

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.papers.edit',
                $request->boolean('exit_after_save') ? $proposalDraft : [$proposalDraft, $paper['slug']],
            )
            ->with('success', $paper['label'].' saved.');
    }

    public function remove(
        ProposalDraft $proposalDraft,
        string $paper,
        int $document,
        ProposalPaperCatalog $catalog,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $this->uploadPaper($catalog, $paper);
        $proposalDraftDocument = $this->documentsFor($proposalDraft, $paper)
            ->findOrFail($document);
        $filePath = $proposalDraftDocument->file_path;

        DB::transaction(function () use ($proposalDraftDocument, $proposalDraft, $paper): void {
            $proposalDraftDocument->delete();
            $this->normalizePositions($proposalDraft, $paper);
        });

        $this->deleteStagedFile($proposalDraft, $filePath);

        return redirect()
            ->route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']])
            ->with('success', $paper['label'].' file removed.');
    }

    public function download(
        ProposalDraft $proposalDraft,
        string $paper,
        int $document,
        ProposalPaperCatalog $catalog,
    ): StreamedResponse {
        Gate::authorize('download', $proposalDraft);

        $paper = $this->uploadPaper($catalog, $paper);
        $proposalDraftDocument = $this->documentsFor($proposalDraft, $paper)
            ->findOrFail($document);

        abort_unless(
            filled($proposalDraftDocument->file_path)
                && Storage::disk('local')->exists($proposalDraftDocument->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $proposalDraftDocument->file_path,
            $proposalDraftDocument->original_filename,
            ['Content-Type' => $proposalDraftDocument->mime_type],
        );
    }

    /** @return array<string, mixed> */
    private function uploadPaper(ProposalPaperCatalog $catalog, string $slug): array
    {
        $paper = $catalog->find($slug);

        abort_unless(is_array($paper) && $paper['mode'] === 'upload', 404);

        return $paper;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @return Builder<ProposalDraftDocument>
     */
    private function documentsFor(ProposalDraft $proposalDraft, array $paper): Builder
    {
        return ProposalDraftDocument::query()
            ->where('proposal_draft_id', $proposalDraft->getKey())
            ->where('document_type', $paper['document_type'])
            ->orderBy('position');
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $paper
     * @return array<int, array<string, mixed>>
     */
    private function storeUploadedFiles(array $files, ProposalDraft $proposalDraft, array $paper): array
    {
        $storedFiles = [];

        try {
            foreach ($files as $file) {
                $extension = Str::lower($file->getClientOriginalExtension());
                $path = $file->storeAs(
                    $proposalDraft->storageDirectory().'/'.$paper['document_type'],
                    Str::uuid().'.'.$extension,
                    'local',
                );

                if (! $path) {
                    throw new RuntimeException('The proposal paper could not be staged.');
                }

                $storedFiles[] = [
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'file_size' => $file->getSize() ?: null,
                    'checksum' => hash_file('sha256', Storage::disk('local')->path($path)) ?: null,
                ];
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(collect($storedFiles)->pluck('file_path')->all());

            throw $exception;
        }

        return $storedFiles;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  array<string, mixed>  $storedFile
     */
    private function replaceDocument(
        ProposalDraft $proposalDraft,
        array $paper,
        array $storedFile,
        int $expectedVersion,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): void {
        $existingDocument = $this->documentsFor($proposalDraft, $paper)->first();
        $oldPath = $existingDocument?->file_path;

        try {
            $saveProposalDraftDocument->handle(
                $proposalDraft,
                $paper['document_type'],
                0,
                $expectedVersion,
                [
                    ...$storedFile,
                    'source_data' => null,
                    'completed_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedFile['file_path']);

            throw $exception;
        }

        if ($oldPath !== $storedFile['file_path']) {
            $this->deleteStagedFile($proposalDraft, $oldPath);
        }
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  array<int, array<string, mixed>>  $storedFiles
     */
    private function appendDocuments(ProposalDraft $proposalDraft, array $paper, array $storedFiles): void
    {
        try {
            DB::transaction(function () use ($proposalDraft, $paper, $storedFiles): void {
                ProposalDraft::query()
                    ->whereKey($proposalDraft->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $documents = $this->documentsFor($proposalDraft, $paper);
                $currentCount = $documents->count();

                if ($currentCount + count($storedFiles) > (int) $paper['max_files']) {
                    throw ValidationException::withMessages([
                        'documents' => 'You may upload no more than '.$paper['max_files'].' curriculum vitae files.',
                    ]);
                }

                $maximumPosition = $documents->max('position');
                $nextPosition = $maximumPosition === null ? 0 : (int) $maximumPosition + 1;

                foreach ($storedFiles as $offset => $storedFile) {
                    $proposalDraft->documents()->create([
                        ...$storedFile,
                        'document_type' => $paper['document_type'],
                        'position' => $nextPosition + $offset,
                        'source_data' => null,
                        'completed_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(collect($storedFiles)->pluck('file_path')->all());

            throw $exception;
        }
    }

    /** @param array<string, mixed> $paper */
    private function normalizePositions(ProposalDraft $proposalDraft, array $paper): void
    {
        $this->documentsFor($proposalDraft, $paper)
            ->get()
            ->each(function (ProposalDraftDocument $document, int $position): void {
                if ($document->position !== $position) {
                    $document->update(['position' => $position]);
                }
            });
    }

    /** @param array<string, mixed> $paper */
    private function activeTemplate(array $paper): ?ProposalTemplate
    {
        if (! filled($paper['template_slug'])) {
            return null;
        }

        $template = ProposalTemplate::query()
            ->active()
            ->where('workflow_stage', ProposalTemplate::STAGE_INITIAL_SUBMISSION)
            ->where('slug', $paper['template_slug'])
            ->first();

        if (! $template || ! Storage::disk('local')->exists($template->file_path)) {
            return null;
        }

        return $template;
    }

    private function deleteStagedFile(ProposalDraft $proposalDraft, ?string $path): void
    {
        if (filled($path) && Str::startsWith($path, $proposalDraft->storageDirectory().'/')) {
            Storage::disk('local')->delete($path);
        }
    }
}
