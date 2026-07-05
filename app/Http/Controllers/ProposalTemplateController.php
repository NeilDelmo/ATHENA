<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ProposalTemplateController extends Controller
{
    public function index()
    {
        $templates = ProposalTemplate::with('uploader')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->each(function (ProposalTemplate $template) {
                $available = Storage::disk('local')->exists($template->file_path);
                $template->setAttribute('file_available', $available);
                $template->setAttribute(
                    'display_file_size',
                    $available ? Storage::disk('local')->size($template->file_path) : $template->file_size,
                );
            });

        return view('research_head.proposal_templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request, true);
        $slug = $this->uniqueSlug($validated['name']);
        $storedFile = $this->storeFile($request->file('document'), $slug);

        try {
            ProposalTemplate::create([
                ...$validated,
                ...$storedFile,
                'slug' => $slug,
                'is_active' => true,
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedFile['file_path']);

            throw $exception;
        }

        return back()->with('success', 'Proposal template uploaded successfully.');
    }

    public function update(Request $request, ProposalTemplate $proposalTemplate)
    {
        $validated = $this->validateTemplate($request, false);
        $oldPath = $proposalTemplate->file_path;
        $storedFile = null;

        if ($request->hasFile('document')) {
            $storedFile = $this->storeFile($request->file('document'), $proposalTemplate->slug);
        }

        try {
            $proposalTemplate->update([
                ...$validated,
                ...($storedFile ?? []),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($storedFile) {
                Storage::disk('local')->delete($storedFile['file_path']);
            }

            throw $exception;
        }

        if ($storedFile && $oldPath !== $storedFile['file_path'] && str_starts_with($oldPath, 'proposal-templates/')) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', 'Proposal template updated successfully.');
    }

    public function updateStatus(Request $request, ProposalTemplate $proposalTemplate)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $proposalTemplate->update([
            'is_active' => $validated['is_active'],
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', $proposalTemplate->is_active
            ? 'Proposal template restored.'
            : 'Proposal template archived.');
    }

    public function download(Request $request, ProposalTemplate $proposalTemplate)
    {
        abort_unless($proposalTemplate->is_active || $request->user()->hasRole('research_head'), 404);
        abort_unless(Storage::disk('local')->exists($proposalTemplate->file_path), 404);

        return Storage::disk('local')->download(
            $proposalTemplate->file_path,
            $proposalTemplate->original_filename,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request, bool $documentRequired): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'revision_label' => ['nullable', 'string', 'max:100'],
            'workflow_stage' => ['required', Rule::in(array_keys(ProposalTemplate::workflowStages()))],
            'document' => [$documentRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:25600'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeFile(UploadedFile $file, string $slug): array
    {
        $path = $file->store('proposal-templates/'.$slug, 'local');
        abort_unless($path, 500, 'The proposal template could not be stored.');

        $realPath = $file->getRealPath();

        return [
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: null,
            'checksum' => $realPath ? hash_file('sha256', $realPath) ?: null : null,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'proposal-template';
        $slug = $baseSlug;
        $suffix = 2;

        while (ProposalTemplate::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
