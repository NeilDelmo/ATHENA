<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\SaveResearchHeadInitialScreeningFormRequest;
use App\Models\ProposalVersion;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\InitialScreeningFormDocumentService;
use App\Support\ResearchHeadScreeningData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchHeadInitialScreeningFormController extends Controller
{
    public function edit(TopicProposal $topic, ProposalVersion $version, ResearchHeadScreeningData $data): View
    {
        $this->authorizeVersion($topic, $version);

        return view('research_head.initial-screening-form.edit', [
            'topic' => $topic, 'version' => $version, 'screeningForm' => $data->forVersion($version),
            'canEdit' => Gate::allows('fillInitialScreeningForm', [$topic, $version]),
        ]);
    }

    public function update(SaveResearchHeadInitialScreeningFormRequest $request, TopicProposal $topic, ProposalVersion $version): RedirectResponse
    {
        DB::transaction(function () use ($request, $topic, $version): void {
            $topic = TopicProposal::query()->lockForUpdate()->findOrFail($topic->id);
            Gate::authorize('fillInitialScreeningForm', [$topic, $version]);
            $version->research_head_screening = [...$request->validated(), 'saved_by' => $request->user()->id];
            $version->save();
        });

        return to_route('research_head.topics.initial-screening-form.edit', [$topic, $version])
            ->with('success', 'Initial Screening Form saved. Download it for printing and wet signatures.');
    }

    public function download(TopicProposal $topic, ProposalVersion $version, ResearchHeadScreeningData $data, InitialScreeningFormDocumentService $documents): StreamedResponse
    {
        $this->authorizeVersion($topic, $version);
        abort_if($version->research_head_screening === null, 409, 'Save the Initial Screening Form before downloading.');
        $contents = $documents->generate($data->forVersion($version));

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, (Str::slug($version->title ?? $topic->title) ?: 'proposal').'-research-head-initial-screening-v'.$version->version_number.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pdf(TopicProposal $topic, ProposalVersion $version, ResearchHeadScreeningData $data, InitialScreeningFormDocumentService $documents, DocumentPdfConverter $converter): Response
    {
        $this->authorizeVersion($topic, $version);
        abort_if($version->research_head_screening === null, 409, 'Save the Initial Screening Form before downloading.');

        return response($converter->convertDocx($documents->generate($data->forVersion($version))), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="research-head-initial-screening-v'.$version->version_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeVersion(TopicProposal $topic, ProposalVersion $version): void
    {
        Gate::authorize('view', $topic);
        abort_unless(auth()->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        abort_unless($version->topic_id === $topic->id, 404);
        $topic->markLatestVersionViewedByResearchHead();
    }
}
