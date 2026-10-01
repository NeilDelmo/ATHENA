<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use App\Services\ResearchHeadReportQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResearchHeadReportReviewController extends Controller
{
    public function index(Request $request, ResearchHeadReportQueue $queue): View
    {
        Gate::authorize('viewAny', TopicProposal::class);
        $filters = $request->validate([
            'type' => ['nullable', 'in:quarterly,progress,terminal'],
            'status' => ['nullable', 'in:pending,revision_requested,reviewed,all'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $type = $filters['type'] ?? '';
        $status = $filters['status'] ?? 'pending';
        $search = trim($filters['search'] ?? '');
        $pendingByType = $queue->query(status: 'pending')->selectRaw('report_type, COUNT(*) as total')->groupBy('report_type')->pluck('total', 'report_type');
        $reports = $queue->query($type, $status === 'all' ? '' : $status, $search)
            ->orderBy('received_at', $status === 'pending' ? 'asc' : 'desc')
            ->orderBy('report_type')->orderBy('id')->paginate(15)->withQueryString();

        return view('research_head.report-reviews.index', compact('reports', 'pendingByType', 'type', 'status', 'search'));
    }
}
