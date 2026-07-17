<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\ProposalPaperCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && ($this->user()?->can('update', $proposalDraft) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ProposalPaperCatalog $catalog): array
    {
        $paper = $this->uploadPaper($catalog);
        $remainingSlots = $this->remainingSlots($paper);

        return [
            'document_version' => [$paper['multiple'] ? 'nullable' : 'required', 'integer', 'min:0'],
            'documents' => ['required', 'array', 'min:1', 'max:'.$remainingSlots],
            'documents.*' => [
                'required',
                'file',
                'extensions:'.implode(',', $paper['accepted_extensions']),
                'mimes:'.implode(',', $paper['accepted_extensions']),
                'mimetypes:'.implode(',', $paper['accepted_mime_types']),
                'max:'.$paper['max_kilobytes'],
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'documents' => 'paper files',
            'documents.*' => 'paper file',
        ];
    }

    /** @return array<string, mixed> */
    private function uploadPaper(ProposalPaperCatalog $catalog): array
    {
        $paper = $catalog->find((string) $this->route('paper'));

        abort_unless(is_array($paper) && $paper['mode'] === 'upload', 404);

        return $paper;
    }

    /** @param array<string, mixed> $paper */
    private function remainingSlots(array $paper): int
    {
        if (! $paper['multiple']) {
            return 1;
        }

        /** @var ProposalDraft $proposalDraft */
        $proposalDraft = $this->route('proposalDraft');
        $currentCount = $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->count();

        return max(0, (int) $paper['max_files'] - $currentCount);
    }
}
