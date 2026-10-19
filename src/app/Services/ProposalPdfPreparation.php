<?php

namespace App\Services;

use App\Actions\SubmitProposalDraft;
use App\Exceptions\ProposalPdfPreparationBusy;
use App\Jobs\PrepareProposalDraftPdfs;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalVersionFile;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalPdfPreparation
{
    private const MAX_ATTEMPTS = 3;

    private const STALLED_AFTER = 600;

    public function __construct(private readonly ProposalDraftReadiness $readiness) {}

    public function request(int $draftId, bool $retry = false): void
    {
        DB::transaction(function () use ($draftId, $retry): void {
            $draft = ProposalDraft::with(['documents', 'researchCall', 'owner'])->lockForUpdate()->find($draftId);
            if (! $draft || ! $draft->owner || ! $draft->isEditable() || $this->readiness->errors($draft) !== [] || $this->readiness->submissionFilesArePrepared($draft)) {
                return;
            }
            $fingerprint = $this->fingerprint($draft);
            $state = $draft->pdf_preparation_state ?? [];
            $sameContent = ($state['fingerprint'] ?? null) === $fingerprint;
            if (! $sameContent && ($state['status'] ?? '') === 'queued' && ($state['attempts'] ?? 0) === 0
                && ($this->jobExists($state['run_id'] ?? '') || ($state['updated_at'] ?? 0) > now()->timestamp - self::STALLED_AFTER)) {
                $this->store($draftId, [...$state, 'fingerprint' => $fingerprint, 'recoveries' => 0, 'updated_at' => now()->timestamp]);

                return;
            }
            $recovering = $sameContent && in_array($state['status'] ?? '', ['queued', 'preparing', 'retrying'], true);
            if ($sameContent && ($state['status'] ?? null) === 'failed' && ! $retry) {
                return;
            }
            if ($recovering) {
                if ($this->jobExists($state['run_id'] ?? '') || ($state['updated_at'] ?? 0) > now()->timestamp - self::STALLED_AFTER) {
                    return;
                }
                if (($state['attempts'] ?? 0) >= self::MAX_ATTEMPTS || ($state['recoveries'] ?? 0) >= self::MAX_ATTEMPTS) {
                    $this->store($draftId, [...$state, 'status' => 'failed']);

                    return;
                }
            }
            $runId = (string) Str::uuid();
            $this->store($draftId, [
                'status' => $recovering ? 'retrying' : 'queued',
                'fingerprint' => $fingerprint,
                'run_id' => $runId,
                'attempts' => $recovering ? ($state['attempts'] ?? 0) : 0,
                'recoveries' => $recovering ? ($state['recoveries'] ?? 0) + 1 : 0,
                'updated_at' => now()->timestamp,
            ]);
            // The token makes an orphaned job harmless if a separate queue database commits first.
            Queue::connection('proposal-pdfs')->later(5, (new PrepareProposalDraftPdfs($draftId, $runId))->beforeCommit(), 'proposal-pdfs');
        });
    }

    public function quietSeconds(int $draftId): int
    {
        $state = ProposalDraft::find($draftId)?->pdf_preparation_state;

        return max(0, now()->timestamp - (int) ($state['updated_at'] ?? 0));
    }

    public function run(int $draftId, ?string $runId = null): void
    {
        $fingerprint = null;
        $attempt = 0;
        try {
            $draft = DB::transaction(function () use ($draftId, &$runId, &$attempt): ?ProposalDraft {
                $draft = ProposalDraft::with(['documents', 'researchCall', 'owner'])->lockForUpdate()->find($draftId);
                $state = $draft?->pdf_preparation_state ?? [];
                if (! $draft || ! $draft->owner || ! $draft->isEditable() || $this->readiness->errors($draft) !== []
                    || ($runId !== null && ($state['run_id'] ?? null) !== $runId)
                    || (($state['status'] ?? '') === 'failed' && ($state['fingerprint'] ?? null) === $this->fingerprint($draft))) {
                    return null;
                }
                $runId ??= $state['run_id'] ?? (string) Str::uuid();
                if ($this->readiness->submissionFilesArePrepared($draft)) {
                    $this->store($draftId, [...$state, 'run_id' => $runId, 'status' => 'ready',
                        'fingerprint' => $this->fingerprint($draft), 'updated_at' => now()->timestamp]);

                    return null;
                }
                if (($state['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                    $this->store($draftId, [...$state, 'status' => 'failed', 'updated_at' => now()->timestamp]);

                    return null;
                }
                $attempt = ($state['attempts'] ?? 0) + 1;
                $this->store($draftId, [...$state, 'run_id' => $runId, 'status' => 'preparing',
                    'attempts' => $attempt, 'fingerprint' => $this->fingerprint($draft), 'updated_at' => now()->timestamp]);

                return $draft;
            });
            if (! $draft) {
                return;
            }
            $fingerprint = $this->fingerprint($draft);
            if (! $this->readiness->submissionFilesArePrepared($draft)) {
                app(SubmitProposalDraft::class)->prepare($draft, $draft->owner, onlyMissing: true);
            }
            $draft->refresh()->load(['documents', 'researchCall']);
            if ($this->readiness->submissionFilesArePrepared($draft)) {
                $this->put($draftId, 'ready', $this->fingerprint($draft), $runId);
            } else {
                $this->request($draftId);
            }
        } catch (ProposalPdfPreparationBusy $exception) {
            $this->put($draftId, 'retrying', $fingerprint ?? '', $runId, max(0, $attempt - 1));

            throw $exception;
        } catch (ValidationException $exception) {
            $latest = ProposalDraft::with('documents')->find($draftId);
            if ($latest !== null && $this->fingerprint($latest) === $fingerprint) {
                $this->put($draftId, 'failed', $fingerprint ?? '', $runId);
                report($exception);
            } else {
                $this->request($draftId);
            }
        } catch (Throwable $exception) {
            $this->put($draftId, $attempt >= self::MAX_ATTEMPTS ? 'failed' : 'retrying', $fingerprint ?? '', $runId);
            if ($attempt < self::MAX_ATTEMPTS) {
                throw $exception;
            }
            report($exception);
        }
    }

    /** @return array{status: string, message: string, prepared: int, total: int} */
    public function status(ProposalDraft $draft): array
    {
        $draft->loadMissing(['documents', 'researchCall']);
        $prepared = $draft->documents->filter(fn (ProposalDraftDocument $document): bool => $document->mime_type === 'application/pdf'
            && filled($document->file_path) && Storage::disk('local')->exists($document->file_path))->count();
        $state = $draft->pdf_preparation_state ?? [];
        $status = match (true) {
            $this->readiness->submissionFilesArePrepared($draft) && $this->readiness->errors($draft) === [] => 'ready',
            ! $draft->isEditable() => 'paused',
            $this->readiness->errors($draft) !== [] => 'incomplete',
            ($state['fingerprint'] ?? null) === $this->fingerprint($draft) && ($state['status'] ?? '') !== 'ready' => $state['status'] ?? 'needed',
            default => 'needed',
        };
        if (in_array($status, ['queued', 'preparing', 'retrying'], true)
            && (($state['updated_at'] ?? 0) < now()->timestamp - self::STALLED_AFTER
                || (($state['updated_at'] ?? 0) < now()->timestamp - 60 && ! $this->workerIsHealthy()))) {
            $status = 'stalled';
        }
        $message = match ($status) {
            'ready' => 'Submission PDFs are up to date.',
            'queued' => 'Changes saved. PDFs will update automatically.',
            'preparing' => 'Preparing the latest submission PDFs…',
            'retrying' => 'PDF preparation was interrupted. It will retry automatically.',
            'stalled' => 'PDF preparation is delayed. Recovery will resume automatically; your saved content is safe.',
            'failed' => 'PDF preparation needs attention. Your saved content is safe. Check required content or retry when ready.',
            'paused' => 'PDF preparation is paused while editing is locked.',
            'incomplete' => 'PDFs will prepare automatically when the required content is complete.',
            default => 'Saved content is complete. PDFs are waiting to prepare.',
        };

        return ['status' => $status, 'message' => $message, 'prepared' => $prepared, 'total' => app(ProposalPaperCatalog::class)->all()->count()];
    }

    public function fail(int $draftId, ?string $runId = null): void
    {
        $draft = ProposalDraft::with('documents')->find($draftId);
        if ($draft) {
            $this->put($draftId, 'failed', $this->fingerprint($draft), $runId);
        }
    }

    public function reconcile(): int
    {
        $checked = 0;
        ProposalDraft::query()->where('status', ProposalDraft::STATUS_DRAFT)->select('id')->chunkById(100, function ($drafts) use (&$checked): void {
            foreach ($drafts as $draft) {
                $this->request($draft->id);
                $checked++;
            }
        });

        return $checked;
    }

    public function heartbeat(): void
    {
        Cache::put('proposal-pdfs:worker-heartbeat', now()->timestamp, self::STALLED_AFTER);
    }

    public function prune(ProposalDraft $draft): void
    {
        $directory = $draft->storageDirectory().'/prepared';
        $referenced = ProposalDraftDocument::where('proposal_draft_id', $draft->id)->pluck('file_path')
            ->merge(ProposalDraftDocumentVersion::where('proposal_draft_id', $draft->id)->pluck('file_path'))
            ->merge(ProposalVersionFile::where('file_path', 'like', $directory.'/%')->pluck('file_path'))
            ->filter()->all();
        // Replacement uploads can be stored before their database transaction commits.
        $obsolete = array_filter(Storage::disk('local')->allFiles($directory), fn (string $path): bool => str_starts_with($path, $directory.'/')
            && ! str_starts_with($path, $directory.'/manual/')
            && ! str_contains($path, '..') && ! in_array($path, $referenced, true));
        Storage::disk('local')->delete(array_values($obsolete));
    }

    private function fingerprint(ProposalDraft $draft): string
    {
        return hash('sha256', json_encode([
            $draft->lock_version,
            $draft->documents->sortBy('id')->map(fn (ProposalDraftDocument $document): array => [$document->id, $document->lock_version])->values()->all(),
            $draft->resolvedSignatorySelections(),
        ], JSON_THROW_ON_ERROR));
    }

    private function jobExists(string $runId): bool
    {
        return $runId !== '' && $this->jobs()->where('payload', 'like', '%'.$runId.'%')->exists();
    }

    private function workerIsHealthy(): bool
    {
        return Cache::get('proposal-pdfs:worker-heartbeat', 0) >= now()->timestamp - 60
            || $this->jobs()->where('reserved_at', '>=', now()->timestamp - self::STALLED_AFTER)->exists();
    }

    private function jobs(): Builder
    {
        return DB::connection(config('queue.connections.proposal-pdfs.connection'))
            ->table(config('queue.connections.proposal-pdfs.table', 'jobs'))->where('queue', 'proposal-pdfs');
    }

    private function put(int $draftId, string $status, string $fingerprint, ?string $runId, ?int $attempts = null): void
    {
        DB::transaction(function () use ($draftId, $status, $fingerprint, $runId, $attempts): void {
            $draft = ProposalDraft::lockForUpdate()->find($draftId);
            $state = $draft?->pdf_preparation_state ?? [];
            if (! $draft || ($runId !== null && ($state['run_id'] ?? null) !== $runId)) {
                return;
            }
            $this->store($draftId, [...$state, 'status' => $status, 'fingerprint' => $fingerprint,
                'attempts' => $attempts ?? ($state['attempts'] ?? 0), 'updated_at' => now()->timestamp]);
        });
    }

    /** @param array<string, mixed> $state */
    private function store(int $draftId, array $state): void
    {
        ProposalDraft::query()->whereKey($draftId)->toBase()->update(['pdf_preparation_state' => json_encode($state, JSON_THROW_ON_ERROR)]);
    }
}
