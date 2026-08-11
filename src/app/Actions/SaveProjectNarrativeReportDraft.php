<?php

namespace App\Actions;

use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProjectNarrativeReportDraft
{
    /** @param  array<string, mixed>  $sourceData */
    public function handle(
        TopicProposal $topic,
        User $user,
        int $expectedVersion,
        array $sourceData,
    ): ProjectNarrativeReportDraft {
        $normalizedSourceData = $this->normalizeSourceData($sourceData);

        return DB::transaction(function () use ($topic, $user, $expectedVersion, $normalizedSourceData): ProjectNarrativeReportDraft {
            $draft = ProjectNarrativeReportDraft::query()
                ->whereBelongsTo($topic, 'topic')
                ->whereBelongsTo($user, 'user')
                ->lockForUpdate()
                ->first();
            $currentVersion = $draft?->lock_version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'draft_version' => 'A newer saved progress-report draft is available. Reload the page before saving again.',
                ]);
            }

            if ($draft === null) {
                return ProjectNarrativeReportDraft::query()->create([
                    'topic_id' => $topic->id,
                    'user_id' => $user->id,
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

    /**
     * @param  array<string, mixed>  $sourceData
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
