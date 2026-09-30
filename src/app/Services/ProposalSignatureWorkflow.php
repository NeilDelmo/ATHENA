<?php

namespace App\Services;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Support\InitialScreeningSubmissionOrder;
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
        return $this->signedCopiesBySource($version)->keys()->values();
    }

    /** @return Collection<int, ProposalVersionFile> */
    public function signedCopiesBySource(ProposalVersion $version): Collection
    {
        $requiredFiles = $this->requiredFiles($version)->keyBy('id');
        $uploads = $version->files
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->reject->isSuperseded()
            ->sortByDesc('id')
            ->unique(fn (ProposalVersionFile $file): string => $file->source_version_file_id.':'.($file->source_data['purpose'] ?? ''))
            ->filter(fn (ProposalVersionFile $file): bool => $requiredFiles->has($file->source_version_file_id)
                && $file->mime_type === 'application/pdf'
                && filled($file->file_path)
                && Storage::disk('local')->exists($file->file_path));

        $assessmentCopies = $uploads
            ->filter(function (ProposalVersionFile $file) use ($requiredFiles): bool {
                $sourceType = $requiredFiles->get($file->source_version_file_id)->document_type;
                $data = $file->source_data ?? [];
                if (($data['target_document_type'] ?? null) !== $sourceType) {
                    return false;
                }

                return match ($sourceType) {
                    ProposalVersionFile::TYPE_GAD_CHECKLIST => ($data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT
                        && ($data['gad_signature_confirmed'] ?? false) === true
                        && (isset($data['gad_outcome'])
                            ? in_array($data['gad_outcome'], ['passed', 'commended'], true)
                            : (is_numeric($data['gad_score'] ?? null) && (float) $data['gad_score'] >= 8)),
                    ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM => ($data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
                        && filled($data['narrative_evaluation'] ?? null)
                        && ($data['recommended_action'] ?? null) === InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
                    default => false,
                };
            })
            ->keyBy('source_version_file_id');

        return $assessmentCopies->replace($uploads
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
            ->keyBy('source_version_file_id'));
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
