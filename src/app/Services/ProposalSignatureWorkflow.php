<?php

namespace App\Services;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProposalSignatureWorkflow
{
    /** @return Collection<int, ProposalVersionFile> */
    public function requiredFiles(ProposalVersion $version): Collection
    {
        $version->loadMissing('files');

        $selectionRecord = $version->files
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['decision'] ?? null) === TopicProposal::STATUS_READY_FOR_SIGNATURE)
            ->sortByDesc('id')
            ->first();
        $selectedFileIds = collect($selectionRecord?->source_data['required_signature_file_ids'] ?? [])
            ->map(fn (mixed $fileId): int => (int) $fileId)
            ->unique();

        return $version->files
            ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->whereIn('id', $selectedFileIds)
            ->values();
    }

    /** @return Collection<int, int> */
    public function signedSourceFileIds(ProposalVersion $version): Collection
    {
        $version->loadMissing('files');

        return $version->files
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED
                && $file->source_version_file_id !== null
                && Storage::disk('local')->exists($file->file_path))
            ->pluck('source_version_file_id')
            ->unique()
            ->values();
    }

    /** @return Collection<int, ProposalVersionFile> */
    public function missingRequiredFiles(ProposalVersion $version): Collection
    {
        $signedSourceFileIds = $this->signedSourceFileIds($version);

        return $this->requiredFiles($version)
            ->reject(fn (ProposalVersionFile $file): bool => $signedSourceFileIds->contains($file->id))
            ->values();
    }

    public function isComplete(ProposalVersion $version): bool
    {
        return $this->requiredFiles($version)->isNotEmpty()
            && $this->missingRequiredFiles($version)->isEmpty();
    }
}
