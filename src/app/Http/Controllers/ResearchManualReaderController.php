<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckResearchManualGuidanceRequest;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\ResearchManualGuidanceCheck;
use App\Services\ResearchManualLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResearchManualReaderController extends Controller
{
    public function __construct(
        private ResearchManualLibrary $library,
        private ResearchManualGuidanceCheck $guidance,
    ) {}

    public function show(Request $request, string $version): View
    {
        $resolved = $this->readableManual($request, $version);

        return view('research-manuals.reader', [
            'manualReader' => $this->library->readerConfig($resolved['index'], $resolved['manual'], $this->isManager($request)),
        ]);
    }

    public function file(Request $request, string $version): BinaryFileResponse|Response
    {
        $resolved = $this->readableManual($request, $version);
        $headers = ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        $filename = 'research-manual-'.$version.'.pdf';

        if (isset($resolved['pdf_content'])) {
            return response($resolved['pdf_content'], 200, [
                ...$headers,
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            ])->setPrivate();
        }

        $response = $request->boolean('download')
            ? response()->download($resolved['pdf_path'], $filename, $headers)
            : response()->file($resolved['pdf_path'], $headers)->setContentDisposition('inline', $filename);

        return $response->setPrivate();
    }

    public function check(CheckResearchManualGuidanceRequest $request, ResearchManual $researchManual): JsonResponse
    {
        $result = $this->guidance->answer($researchManual, $request->validated('question'));

        return response()->json($result['payload'], $result['status']);
    }

    /** @return array{manual: ?ResearchManual, index: array<string, mixed>, pdf_path: string, pdf_content?: string} */
    private function readableManual(Request $request, string $version): array
    {
        abort_unless($request->user() !== null, 403);

        try {
            $resolved = $this->library->resolveVersion($version);
        } catch (RuntimeException) {
            abort(503, 'This research manual is unavailable. Please contact the research office or try again later.');
        }

        abort_if($resolved === null, 404, 'This research manual edition could not be found.');
        abort_unless($resolved['manual'] === null || $resolved['manual']->activated_at !== null || $this->isManager($request), 403);

        return $resolved;
    }

    private function isManager(Request $request): bool
    {
        return $request->user()?->hasRole('research_head')
            && $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD);
    }
}
