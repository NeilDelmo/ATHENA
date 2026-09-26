<?php

namespace App\Actions;

use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreProjectDocuments
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ProjectDocument>
     */
    public function handle(
        TopicProposal $topic,
        User $uploader,
        array $files,
        string $category,
        ?string $note,
    ): Collection {
        $storedPaths = collect();

        try {
            return DB::transaction(function () use ($topic, $uploader, $files, $category, $note, $storedPaths): Collection {
                return collect($files)->map(function (UploadedFile $file) use ($topic, $uploader, $category, $note, $storedPaths): ProjectDocument {
                    $path = $file->store('project-documents/'.$topic->id, 'local');

                    if (! $path) {
                        throw new RuntimeException('The project PDF could not be stored.');
                    }

                    $storedPaths->push($path);
                    $realPath = $file->getRealPath();

                    return $topic->projectDocuments()->create([
                        'uploaded_by' => $uploader->id,
                        'category' => $category,
                        'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                        'note' => filled($note) ? $note : null,
                        'file_path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize() ?: null,
                        'checksum' => $realPath ? hash_file('sha256', $realPath) ?: null : null,
                    ]);
                });
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths->all());

            throw $exception;
        }
    }
}
