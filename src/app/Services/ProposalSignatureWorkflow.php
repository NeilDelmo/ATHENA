<?php

namespace App\Services;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProposalSignatureWorkflow
{
    public const REQUIRED_DOCUMENT_TYPES = [
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
    ];

    /** @return Collection<int, ProposalVersionFile> */
    public function requiredFiles(ProposalVersion $version): Collection
    {
        $version->loadMissing('files');

        return $version->files
            ->whereIn('document_type', self::REQUIRED_DOCUMENT_TYPES)
            ->values();
    }

    public function hasRequiredPapers(ProposalVersion $version): bool
    {
        return collect(self::REQUIRED_DOCUMENT_TYPES)
            ->diff($this->requiredFiles($version)->pluck('document_type'))
            ->isEmpty();
    }

    /** @return Collection<int, int> */
    public function signedSourceFileIds(ProposalVersion $version): Collection
    {
        $version->loadMissing('files');

        return $version->files
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED
                && $file->source_version_file_id !== null
                && ! $file->isSuperseded()
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
        return $this->hasRequiredPapers($version)
            && $this->missingRequiredFiles($version)->isEmpty();
    }
}
