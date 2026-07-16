<?php

namespace App\Services;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProposalPackageService
{
    /**
     * Store every package file present in the request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function storeFromRequest(Request $request, string $directory): array
    {
        $storedFiles = [];

        try {
            $primary = $request->file('detailed_proposal') ?: $request->file('document');
            if ($primary instanceof UploadedFile) {
                $storedFiles[] = $this->storeFile(
                    $primary,
                    $directory.'/detailed-proposal',
                    ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
                );
            }

            foreach ([
                'work_plan' => ProposalVersionFile::TYPE_WORK_PLAN,
                'line_item_budget' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
                'expense_breakdown' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
                'gad_checklist' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'comment_response' => ProposalVersionFile::TYPE_COMMENT_RESPONSE,
            ] as $input => $documentType) {
                $file = $request->file($input);

                if ($file instanceof UploadedFile) {
                    $storedFiles[] = $this->storeFile($file, $directory.'/'.$input, $documentType);
                }
            }

            foreach ($request->file('curricula_vitae', []) as $position => $curriculumVitae) {
                if ($curriculumVitae instanceof UploadedFile) {
                    $storedFiles[] = $this->storeFile(
                        $curriculumVitae,
                        $directory.'/curricula-vitae',
                        ProposalVersionFile::TYPE_CURRICULUM_VITAE,
                        $position,
                    );
                }
            }
        } catch (Throwable $exception) {
            $this->deleteStored($storedFiles);

            throw $exception;
        }

        return $storedFiles;
    }

    /**
     * Create a complete revision snapshot, carrying forward files that were not replaced.
     *
     * @param  array<int, array<string, mixed>>  $replacements
     * @return array<int, array<string, mixed>>
     */
    public function revisionSnapshot(?ProposalVersion $previousVersion, array $replacements, TopicProposal $topic): array
    {
        $previousFiles = $previousVersion?->files ?? collect();
        $snapshot = [];

        foreach ([
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            ProposalVersionFile::TYPE_WORK_PLAN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
            ProposalVersionFile::TYPE_COMMENT_RESPONSE,
        ] as $documentType) {
            $replacementFiles = collect($replacements)
                ->where('document_type', $documentType)
                ->values();

            if ($replacementFiles->isNotEmpty()) {
                array_push($snapshot, ...$replacementFiles->all());

                continue;
            }

            $carriedFiles = $previousFiles
                ->where('document_type', $documentType)
                ->map(fn (ProposalVersionFile $file) => $this->carriedFileAttributes($file))
                ->values();

            if ($carriedFiles->isNotEmpty()) {
                array_push($snapshot, ...$carriedFiles->all());
            }
        }

        if (! collect($snapshot)->contains('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)) {
            $legacyPath = $previousVersion?->file_path ?: ($topic->final_file_path ?: $topic->initial_file_path);

            if ($legacyPath) {
                $snapshot[] = [
                    'source_version_file_id' => null,
                    'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
                    'position' => 0,
                    'file_path' => $legacyPath,
                    'original_filename' => $previousVersion?->original_filename ?: basename($legacyPath),
                    'mime_type' => $previousVersion?->mime_type,
                    'file_size' => $previousVersion?->file_size,
                    'checksum' => $previousVersion?->checksum,
                    'is_carried_forward' => true,
                ];
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<string, mixed>
     */
    public function primaryFile(array $files): array
    {
        $primary = collect($files)->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);

        if (! $primary) {
            throw new RuntimeException('A detailed proposal document is required for every version.');
        }

        return $primary;
    }

    /**
     * Delete newly stored files after a failed operation.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    public function deleteStored(array $files): void
    {
        Storage::disk('local')->delete(collect($files)->pluck('file_path')->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function storeGeneratedWorkPlan(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';
        $originalFilename = $filenameBase.'-work-plan.docx';
        $path = $directory.'/work-plan/'.Str::uuid().'.docx';

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The generated Work Plan could not be stored.');
        }

        return [
            'source_version_file_id' => null,
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'is_carried_forward' => false,
            'source_data' => $sourceData,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedLineItemBudget(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';
        $originalFilename = $filenameBase.'-line-item-budget.docx';
        $path = $directory.'/line-item-budget/'.Str::uuid().'.docx';

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The generated Line-Item Budget could not be stored.');
        }

        return [
            'source_version_file_id' => null,
            'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'is_carried_forward' => false,
            'source_data' => $sourceData,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedCurriculumVitae(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';
        $originalFilename = $filenameBase.'-curriculum-vitae.docx';
        $path = $directory.'/curriculum-vitae/'.Str::uuid().'.docx';

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The generated Curriculum Vitae could not be stored.');
        }

        return [
            'source_version_file_id' => null,
            'document_type' => ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'is_carried_forward' => false,
            'source_data' => $sourceData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeFile(UploadedFile $file, string $directory, string $documentType, int $position = 0): array
    {
        $path = $file->store($directory, 'local');

        if (! $path) {
            throw new RuntimeException('A proposal package file could not be stored.');
        }

        $realPath = $file->getRealPath();

        return [
            'source_version_file_id' => null,
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: null,
            'checksum' => $realPath ? hash_file('sha256', $realPath) ?: null : null,
            'is_carried_forward' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function carriedFileAttributes(ProposalVersionFile $file): array
    {
        return [
            'source_version_file_id' => $file->id,
            'document_type' => $file->document_type,
            'position' => $file->position,
            'file_path' => $file->file_path,
            'original_filename' => $file->original_filename,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'checksum' => $file->checksum,
            'is_carried_forward' => true,
            'source_data' => $file->source_data,
        ];
    }
}
