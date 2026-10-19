<?php

namespace App\Services;

use App\Jobs\CheckResearchManualGuidance;
use App\Models\ResearchManual;
use Throwable;

class ResearchManualChecks
{
    public function __construct(
        private ResearchManualLibrary $library,
        private ResearchManualGuidanceCheck $guidance,
        private ResearchKnowledgeService $knowledge,
    ) {}

    public function queue(ResearchManual $manual): void
    {
        if (ResearchManual::whereKey($manual->id)->whereNull('guidance_check_status')->update(['guidance_check_status' => 'pending']) === 0) {
            return;
        }
        $manual->refresh();
        $this->dispatch($manual->id);
    }

    public function run(int $manualId): void
    {
        $manual = ResearchManual::find($manualId);
        if ($manual === null || ! in_array($manual->guidance_check_status, ['pending', 'running'], true)) {
            return;
        }

        try {
            $index = $this->library->index($manual);
            $results = $manual->guidance_check_results ?? [
                'version' => $manual->version,
                'unindexed_pages' => $index['unindexed_pages'],
                'checks' => array_map(fn (string $question): array => ['question' => $question, 'status' => 'pending'],
                    $this->library->guidanceQuestions($index, $manual, includeMissing: true)),
            ];
            $manual->forceFill([
                'guidance_check_status' => 'running', 'guidance_check_results' => $results,
                'guidance_check_started_at' => $manual->guidance_check_started_at ?? now(),
            ])->save();

            foreach ($results['checks'] as &$check) {
                if ($check['status'] !== 'pending') {
                    continue;
                }
                $response = $this->guidance->answer($manual, $check['question'], timeout: 20);
                $payload = $response['payload'];
                $sources = $payload['sources'] ?? [];
                $check['sources'] = $this->knowledge->publicSources($sources);
                $check['reply'] = $payload['reply'] ?? null;
                $check['message'] = $payload['message'] ?? null;
                if (($payload['check_type'] ?? null) === 'invalid_evidence') {
                    $check['status'] = 'invalid_references';
                } elseif ($response['status'] !== 200) {
                    $check['status'] = 'unavailable';
                } elseif ($payload['check_type'] === 'no_evidence') {
                    $check['status'] = 'no_evidence';
                } else {
                    preg_match_all('/\[(ATHENA \d+)\]/', $check['reply'], $references);
                    $check['status'] = $references[1] !== [] && array_diff($references[1], array_column($sources, 'reference')) === []
                        ? 'checked' : 'invalid_references';
                }
                break;
            }
            unset($check);
            $complete = ! in_array('pending', array_column($results['checks'], 'status'), true);
            $manual->forceFill([
                'guidance_check_results' => $results,
                'guidance_check_status' => $complete ? 'completed' : 'running',
                'guidance_check_completed_at' => $complete ? now() : null,
            ])->save();
            if (! $complete) {
                $this->dispatch($manualId);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($manualId);
        }
    }

    public function fail(int $manualId): void
    {
        $manual = ResearchManual::find($manualId);
        if ($manual !== null && in_array($manual->guidance_check_status, ['pending', 'running'], true)) {
            $manual->forceFill([
                'guidance_check_status' => 'failed', 'guidance_check_completed_at' => now(),
                'guidance_check_results' => [...($manual->guidance_check_results ?? []),
                    'message' => 'Automated checking stopped because the edition or checking service is unavailable. Existing results are retained. Manual activation is unchanged.',
                ],
            ])->save();
        }
    }

    private function dispatch(int $manualId): void
    {
        try {
            CheckResearchManualGuidance::dispatch($manualId)->afterCommit();
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($manualId);
        }
    }
}
