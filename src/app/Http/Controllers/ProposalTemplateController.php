<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalTemplateController extends Controller
{
    public function download(Request $request, ProposalTemplate $proposalTemplate): StreamedResponse
    {
        abort_unless($proposalTemplate->is_active || $request->user()->isUsingWorkspace('research_head'), 404);
        abort_unless(Storage::disk('local')->exists($proposalTemplate->file_path), 404);

        return Storage::disk('local')->download(
            $proposalTemplate->file_path,
            $proposalTemplate->original_filename,
        );
    }

    public function showSample(string $sample): StreamedResponse
    {
        $definition = config('proposal_samples.'.$sample);

        abort_unless(is_array($definition) && isset($definition['path']), 404);
        abort_unless(Storage::disk('local')->exists($definition['path']), 404);

        return Storage::disk('local')->response(
            $definition['path'],
            basename($definition['path']),
            ['Content-Disposition' => 'inline; filename="'.basename($definition['path']).'"'],
        );
    }
}
