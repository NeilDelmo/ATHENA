<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
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
    public function __construct(private readonly DocumentPdfConverter $pdfConverter) {}

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
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
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

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/work-plan',
            $filenameBase.'-work-plan.pdf',
            ProposalVersionFile::TYPE_WORK_PLAN,
            $sourceData,
        );
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedDetailedProposal(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/detailed-proposal',
            $filenameBase.'-detailed-research-proposal.pdf',
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            $sourceData,
        );
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

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/line-item-budget',
            $filenameBase.'-line-item-budget.pdf',
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            $sourceData,
        );
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedExpenseBreakdown(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/expense-breakdown',
            $filenameBase.'-estimated-expense-breakdown.pdf',
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            $sourceData,
            'xlsx',
        );
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

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/curriculum-vitae',
            $filenameBase.'-curriculum-vitae.pdf',
            ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            $sourceData,
        );
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedGADChecklist(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/gad-checklist',
            $filenameBase.'-gad-checklist.pdf',
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
            $sourceData,
        );
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    public function storeGeneratedInitialScreeningForm(
        string $contents,
        string $directory,
        string $projectTitle,
        ?array $sourceData = null,
    ): array {
        $filenameBase = Str::slug($projectTitle) ?: 'research-project';

        return $this->storeGeneratedPdf(
            $contents,
            $directory.'/initial-screening-form',
            $filenameBase.'-initial-screening-form.pdf',
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
            $sourceData,
        );
    }

    /**
     * @param  array<string, mixed>|null  $sourceData
     * @return array<string, mixed>
     */
    private function storeGeneratedPdf(
        string $sourceContents,
        string $directory,
        string $originalFilename,
        string $documentType,
        ?array $sourceData,
        string $sourceFormat = 'docx',
    ): array {
        $pdfContents = match ($sourceFormat) {
            'docx' => $this->pdfConverter->convertDocx($sourceContents),
            'xlsx' => $this->pdfConverter->convertXlsx($sourceContents),
            default => throw new RuntimeException('The generated paper format cannot be converted to PDF.'),
        };
        $path = $directory.'/'.Str::uuid().'.pdf';

        if (! Storage::disk('local')->put($path, $pdfContents)) {
            throw new RuntimeException('The generated PDF could not be stored.');
        }

        return [
            'source_version_file_id' => null,
            'document_type' => $documentType,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContents),
            'checksum' => hash('sha256', $pdfContents),
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

    /**
     * Store a signed copy of one of the seven required papers uploaded by the Research Head.
     *
     * @param  array{target_document_type: string, note?: string|null}  $meta
     * @return array<string, mixed>
     */
    public function storeHeadUpload(
        UploadedFile $file,
        string $directory,
        array $meta,
    ): array {
        $path = $file->store($directory.'/head-uploads', 'local');

        if (! $path) {
            throw new RuntimeException('The Research Head upload could not be stored.');
        }

        $realPath = $file->getRealPath();

        return [
            'source_version_file_id' => null,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: null,
            'checksum' => $realPath ? hash_file('sha256', $realPath) ?: null : null,
            'is_carried_forward' => false,
            'source_data' => [
                'target_document_type' => $meta['target_document_type'],
                'note' => $meta['note'] ?? null,
            ],
        ];
    }
}
