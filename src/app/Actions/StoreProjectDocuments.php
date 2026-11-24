<?php

namespace App\Actions;

use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreProjectDocuments
{
    public function copy(TopicProposal $topic, User $uploader, string $source, string $originalName, string $category, string $note, string $subdirectory): ProjectDocument
    {
        $disk = Storage::disk('local');
        $path = 'project-documents/'.$topic->id.'/'.$subdirectory.'/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
        if (! $disk->copy($source, $path)) {
            throw new RuntimeException('The existing project file could not be copied.');
        }

        try {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('The copied project file could not be read.');
            }
            try {
                $checksum = hash_init('sha256');
                hash_update_stream($checksum, $stream);
            } finally {
                fclose($stream);
            }

            return $topic->projectDocuments()->create([
                'uploaded_by' => $uploader->id,
                'category' => $category,
                'title' => pathinfo($originalName, PATHINFO_FILENAME),
                'note' => $note,
                'file_path' => $path,
                'original_filename' => $originalName,
                'mime_type' => $disk->mimeType($path),
                'file_size' => $disk->size($path),
                'checksum' => hash_final($checksum),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

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
        ?string $subdirectory = null,
    ): Collection {
        $storedPaths = collect();

        try {
            return DB::transaction(function () use ($topic, $uploader, $files, $category, $note, $storedPaths, $subdirectory): Collection {
                return collect($files)->map(function (UploadedFile $file) use ($topic, $uploader, $category, $note, $storedPaths, $subdirectory): ProjectDocument {
                    $path = $file->store('project-documents/'.$topic->id.($subdirectory ? '/'.$subdirectory : ''), 'local');

                    if (! $path) {
                        throw new RuntimeException('The project file could not be stored.');
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
