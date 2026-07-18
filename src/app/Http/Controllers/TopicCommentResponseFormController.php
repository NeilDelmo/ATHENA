<?php

namespace App\Http\Controllers;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFormDocumentService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopicCommentResponseFormController extends Controller
{
    public function preview(TopicProposal $topic): View
    {
        Gate::authorize('generateCommentResponseForm', $topic);

        $commentResponseForm = $this->commentResponseFormData($topic);

        return view('faculty.comment-response-form.preview', compact('commentResponseForm'));
    }

    public function download(
        TopicProposal $topic,
        CommentResponseFormDocumentService $documentService,
    ): StreamedResponse {
        Gate::authorize('generateCommentResponseForm', $topic);

        $contents = $documentService->generate($this->commentResponseFormData($topic));
        $filenameBase = Str::slug($topic->title) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-comment-response-form.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /**
     * @return array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }
     */
    private function commentResponseFormData(TopicProposal $topic): array
    {
        $topic->loadMissing(['user:id,name,college', 'latestVersion.files']);
        $files = $topic->latestVersion?->files ?? collect();
        $detailedProposal = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ));
        $lineItemBudget = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ));
        $initialScreeningForm = $this->sourceData($files->firstWhere(
            'document_type',
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        ));

        return [
            'project_title' => (string) $topic->title,
            'project_leader' => $this->firstFilled(
                $detailedProposal['project_leader'] ?? null,
                $lineItemBudget['project_leader'] ?? null,
                $initialScreeningForm['project_leader'] ?? null,
                $topic->user?->name,
            ),
            'leader_campus' => $this->firstFilled(
                $detailedProposal['proponent_campus'] ?? null,
                $lineItemBudget['leader_campus'] ?? null,
            ),
            'leader_college' => $this->firstFilled(
                $this->collegeLabel($detailedProposal['proponent_college'] ?? null),
                $this->collegeLabel($lineItemBudget['leader_college'] ?? null),
                $this->collegeLabel($topic->user?->college),
            ),
            'leader_department' => $this->firstFilled(
                $detailedProposal['proponent_department'] ?? null,
            ),
            'staff' => $this->staffRows($detailedProposal, $lineItemBudget),
        ];
    }

    /** @return array<string, mixed> */
    private function sourceData(?ProposalVersionFile $file): array
    {
        return is_array($file?->source_data) ? $file->source_data : [];
    }

    private function firstFilled(mixed ...$values): string
    {
        return collect($values)
            ->map(fn (mixed $value): string => Str::squish((string) $value))
            ->first(fn (string $value): bool => $value !== '', '');
    }

    /**
     * @param  array<string, mixed>  $detailedProposal
     * @param  array<string, mixed>  $lineItemBudget
     * @return list<array{name: string, campus: string, college: string, department: string}>
     */
    private function staffRows(array $detailedProposal, array $lineItemBudget): array
    {
        $detailedStaff = collect(Arr::wrap($detailedProposal['staff'] ?? []))
            ->filter(fn (mixed $member): bool => is_array($member) && filled($member['name'] ?? null));
        $budgetStaff = collect(Arr::wrap($lineItemBudget['staff'] ?? []))
            ->filter(fn (mixed $member): bool => is_array($member) && filled($member['name'] ?? null));

        return $budgetStaff
            ->concat($detailedStaff)
            ->unique(fn (array $member): string => Str::of((string) $member['name'])->squish()->lower()->toString())
            ->take((int) config('comment_response_form.maximum_staff'))
            ->map(function (array $member) use ($budgetStaff, $detailedStaff): array {
                $normalizedName = Str::of((string) $member['name'])->squish()->lower()->toString();
                $budgetMember = $budgetStaff->first(
                    fn (array $candidate): bool => Str::of((string) $candidate['name'])->squish()->lower()->toString() === $normalizedName,
                );
                $detailedMember = $detailedStaff->first(
                    fn (array $candidate): bool => Str::of((string) $candidate['name'])->squish()->lower()->toString() === $normalizedName,
                );

                return [
                    'name' => $this->firstFilled($budgetMember['name'] ?? null, $detailedMember['name'] ?? null),
                    'campus' => $this->firstFilled($budgetMember['campus'] ?? null),
                    'college' => $this->collegeLabel($budgetMember['college'] ?? null),
                    'department' => '',
                ];
            })
            ->values()
            ->all();
    }

    private function collegeLabel(mixed $college): string
    {
        $college = $this->firstFilled($college);
        $acronym = array_search($college, User::COLLEGES, true);

        return is_string($acronym) ? $acronym : $college;
    }
}
