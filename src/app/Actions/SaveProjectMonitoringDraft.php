<?php

namespace App\Actions;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProjectMonitoringDraft
{
    /** @param  array<string, mixed>  $sourceData */
    public function handle(
        TopicProposal $topic,
        User $user,
        ?ProjectProgressReport $sourceReport,
        int $expectedVersion,
        array $sourceData,
    ): ProjectMonitoringDraft {
        $normalizedSourceData = $this->normalizeSourceData($sourceData);

        return DB::transaction(function () use ($topic, $user, $sourceReport, $expectedVersion, $normalizedSourceData): ProjectMonitoringDraft {
            $draft = ProjectMonitoringDraft::query()
                ->whereBelongsTo($topic, 'topic')
                ->whereBelongsTo($user, 'user')
                ->forSource($sourceReport)
                ->lockForUpdate()
                ->first();
            $currentVersion = $draft?->lock_version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'draft_version' => 'A newer saved monitoring draft is available. Reload the page before saving again.',
                ]);
            }

            if ($draft === null) {
                return ProjectMonitoringDraft::query()->create([
                    'topic_id' => $topic->id,
                    'user_id' => $user->id,
                    'source_report_id' => $sourceReport?->id,
                    'source_key' => ProjectMonitoringDraft::sourceKey($sourceReport),
                    'source_data' => $normalizedSourceData,
                    'lock_version' => 1,
                ]);
            }

            if ($this->normalizeSourceData($draft->source_data ?? []) === $normalizedSourceData) {
                return $draft;
            }

            $draft->update([
                'source_data' => $normalizedSourceData,
                'lock_version' => $currentVersion + 1,
            ]);

            return $draft->refresh();
        }, 3);
    }

    /** @param  array<string, mixed>  $sourceData
     * @return array<string, mixed>
     */
    private function normalizeSourceData(array $sourceData): array
    {
        ksort($sourceData);

        foreach ($sourceData as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $sourceData[$key] = array_is_list($value)
                ? array_map(
                    fn (mixed $item): mixed => is_array($item) ? $this->normalizeSourceData($item) : $item,
                    $value,
                )
                : $this->normalizeSourceData($value);
        }

        return $sourceData;
    }
}
