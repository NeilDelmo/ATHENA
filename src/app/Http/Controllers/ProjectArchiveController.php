<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use App\Services\ProjectArchive;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectArchiveController extends Controller
{
    public function __invoke(Request $request, TopicProposal $topic, ProjectArchive $archive): BinaryFileResponse
    {
        return $archive->download($topic, $request->user());
    }
}
