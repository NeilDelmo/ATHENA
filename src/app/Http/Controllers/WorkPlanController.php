<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewWorkPlanRequest;
use App\Services\WorkPlanDocumentService;
use App\Support\WorkPlanData;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkPlanController extends Controller
{
    public function preview(PreviewWorkPlanRequest $request): View
    {
        $workPlan = WorkPlanData::fromValidated($request->validated());

        return view('faculty.work-plans.preview', compact('workPlan'));
    }

    public function download(
        PreviewWorkPlanRequest $request,
        WorkPlanDocumentService $documentService,
    ): StreamedResponse {
        $workPlan = WorkPlanData::fromValidated($request->validated());
        $contents = $documentService->generate($workPlan);
        $filenameBase = Str::slug($workPlan['project_title']) ?: 'research-project';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filenameBase.'-work-plan.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
