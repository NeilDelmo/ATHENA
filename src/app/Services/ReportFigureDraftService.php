<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProgressReportData;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ReportFigureDraftService
{
    /** @return array<string, array<mixed>> */
    public function rules(FormRequest $request): array
    {
        $rule = ['nullable', 'uuid', Rule::in(array_keys($this->available($request)))];
        $rules = ['figures.*.image_id' => $rule, 'saved_cover_image' => $rule];
        foreach (range(1, 30) as $index) {
            $rules['saved_photo_'.$index] = $rule;
        }

        return $rules;
    }

    /** @return array<string, array<string, mixed>> */
    public function available(FormRequest $request): array
    {
        if ($request->attributes->has('report_figure_images')) {
            return $request->attributes->get('report_figure_images');
        }
        $topic = $request->route('topic');
        if (! $topic instanceof TopicProposal || ! $request->user()) {
            return [];
        }
        $type = $request->input('report_type', 'progress');
        $draft = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->where('report_type', $type)->first();
        $previous = $draft?->source_data['saved_images'] ?? [];
        $request->attributes->set('report_figure_previous_paths', array_column($previous, 'path'));
        $images = [];
        if ($type === 'terminal') {
            $images = $previous;
        } else {
            $date = $request->input('reporting_date');
            $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if ($parsed && $parsed->format('Y-m-d') === $date) {
                $schedule = app(MonitoringQuarterService::class);
                $period = $schedule->forDate($date, $topic);
                $savedDate = $draft?->source_data['reporting_date'] ?? null;
                if ($savedDate && $schedule->forDate($savedDate, $topic)['start']->eq($period['start'])) {
                    $images = $previous;
                }
                $revision = $topic->narrativeReports()->submitted()->where('report_type', 'progress')->where('reporting_quarter', $period['quarter'])
                    ->where('review_status', ProjectNarrativeReport::STATUS_REVISION_REQUESTED)->latest('id')->first();
                $images = [...($revision ? $this->reportImages($revision)['saved_images'] : []), ...$images];
            }
        }
        $images = array_filter($images, fn (array $image): bool => $this->isStored($topic, $image));
        $request->attributes->set('report_figure_images', $images);

        return $images;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function withSavedImages(FormRequest $request, array $data): array
    {
        return [...$data, 'saved_images' => $this->available($request)];
    }

    /** @param array<string, mixed> $data */
    public function store(FormRequest $request, TopicProposal $topic, array $data, Closure $save): ProjectNarrativeReportDraft
    {
        $images = $this->available($request);
        $retained = [];
        $stored = [];
        try {
            foreach ($this->slots($data) as [$fileKey, $idKey]) {
                $id = data_get($data, $idKey);
                $file = $request->file($fileKey);
                if ($file instanceof UploadedFile) {
                    $id = (string) Str::uuid();
                    $path = $file->store('narrative-progress-reports/'.$topic->id.'/draft-images', 'local');
                    if (! is_string($path)) {
                        throw new RuntimeException('The report image could not be saved.');
                    }
                    $stored[] = $path;
                    $images[$id] = ['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
                    data_set($data, $idKey, $id);
                }
                if (is_string($id) && isset($images[$id])) {
                    $retained[$id] = $images[$id];
                }
                data_forget($data, $fileKey);
            }
            $data['saved_images'] = $retained;
            $draft = $save($data);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
        $this->deleteUnreferenced($topic, $request->attributes->get('report_figure_previous_paths', []));

        return $draft;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function displayImages(TopicProposal $topic, array $data): array
    {
        $images = [];
        foreach ($this->slots($data) as [$fileKey, $idKey]) {
            $id = data_get($data, $idKey);
            $image = is_string($id) ? ($data['saved_images'][$id] ?? null) : null;
            if ($image && $this->isStored($topic, $image)) {
                data_set($images, $fileKey, ['id' => $id, 'name' => $image['original_name'] ?? basename($image['path']), 'preview_url' => $image['preview_url'] ?? route('project-narrative-reports.draft-images', [$topic, $id, 'preview' => 1])]);
            }
        }

        return $images;
    }

    /** @return array<string, mixed> */
    public function reportImages(ProjectNarrativeReport $report): array
    {
        $data = ['figures' => [], 'saved_images' => []];
        foreach ($report->photos ?? [] as $index => $photo) {
            $hash = md5('report:'.$report->id.':'.$index);
            $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20);
            $data['saved_images'][$id] = [...$photo, 'preview_url' => route('project-narrative-reports.photos.view', [$report, $index, 'preview' => 1]), 'original_url' => route('project-narrative-reports.photos.view', [$report, $index])];
            $data['figures'][] = ['image_id' => $id, 'caption' => $photo['caption'], 'section' => $photo['section'], 'after_paragraph' => $photo['after_paragraph'] ?? 0];
        }

        return $data;
    }

    /** @param array<string, mixed> $image @param array<string, mixed> $photo @param list<string> $storedPaths @return array<string, mixed> */
    public function photo(TopicProposal $topic, string $id, array $image, array $photo, bool $preview, array &$storedPaths): array
    {
        if ($preview) {
            return [...$photo, 'preview_url' => $image['preview_url'] ?? route('project-narrative-reports.draft-images', [$topic, $id, 'preview' => 1]), 'original_url' => $image['original_url'] ?? route('project-narrative-reports.draft-images', [$topic, $id])];
        }
        $path = 'narrative-progress-reports/'.$topic->id.'/'.Str::uuid().'.'.pathinfo($image['path'], PATHINFO_EXTENSION);
        if (! Storage::disk('local')->copy($image['path'], $path)) {
            throw new RuntimeException('The saved report image could not be copied.');
        }
        $storedPaths[] = $path;

        return [...$photo, ...array_intersect_key($image, array_flip(['original_name', 'mime_type', 'size'])), 'path' => $path];
    }

    public function restoreDraft(ProjectNarrativeReport $report, User $user): void
    {
        if (ProjectNarrativeReportDraft::query()->whereBelongsTo($report->topic, 'topic')->whereBelongsTo($user, 'user')->where('report_type', $report->report_type)->exists()) {
            throw ValidationException::withMessages([
                'preparation' => 'This report cannot be discarded because you already have a saved draft of this type. Your saved draft and the prepared report, including its PDF and images, have both been kept.',
            ])->errorBag('narrativeProgress');
        }
        $data = $report->only(['report_type', 'tracking_number', 'researchers', 'funding_agency', 'accomplishments', 'introduction', 'rationale', 'objectives', 'methodology', 'results_discussion', 'terminal_data']);
        foreach (['reporting_date', 'submission_date', 'implementation_start', 'implementation_end', 'prepared_by_date_signed'] as $field) {
            $data[$field] = $report->$field?->toDateString();
        }
        $data['saved_images'] = [];
        $stored = [];
        try {
            $figure = 1;
            foreach ($report->photos ?? [] as $photo) {
                $id = (string) Str::uuid();
                $path = 'narrative-progress-reports/'.$report->topic_id.'/draft-images/'.$id.'.'.pathinfo($photo['path'], PATHINFO_EXTENSION);
                if (! Storage::disk('local')->copy($photo['path'], $path)) {
                    throw new RuntimeException('The report image could not be restored to the draft.');
                }
                $stored[] = $path;
                $data['saved_images'][$id] = [...array_intersect_key($photo, array_flip(['original_name', 'mime_type', 'size'])), 'path' => $path];
                if ($report->report_type === 'progress') {
                    $data['figures'][] = ['image_id' => $id, 'caption' => $photo['caption'], 'section' => $photo['section'], 'after_paragraph' => $photo['after_paragraph'] ?? 0];
                } elseif (($photo['section'] ?? null) === 'cover') {
                    $data['saved_cover_image'] = $id;
                    $data['cover_image_caption'] = $photo['caption'];
                } else {
                    $data['saved_photo_'.$figure] = $id;
                    $data['photo_caption_'.$figure] = $photo['caption'];
                    $data['photo_section_'.$figure] = $photo['section'];
                    $data['photo_after_paragraph_'.$figure] = $photo['after_paragraph'] ?? 0;
                    $figure++;
                }
            }
            ProjectNarrativeReportDraft::create(['topic_id' => $report->topic_id, 'user_id' => $user->id, 'report_type' => $report->report_type, 'source_data' => $data, 'lock_version' => 1]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }

    /** @param list<string> $paths */
    public function deleteUnreferenced(TopicProposal $topic, array $paths): void
    {
        $referenced = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->pluck('source_data')
            ->flatMap(fn (array $data): array => array_column($data['saved_images'] ?? [], 'path'))->all();
        Storage::disk('local')->delete(array_values(array_filter($paths, fn (string $path): bool => str_starts_with($path, 'narrative-progress-reports/'.$topic->id.'/draft-images/') && ! str_contains($path, '..') && ! in_array($path, $referenced, true))));
    }

    /** @param array<string, mixed> $image */
    public function isStored(TopicProposal $topic, array $image): bool
    {
        return is_string($image['path'] ?? null) && str_starts_with($image['path'], 'narrative-progress-reports/'.$topic->id.'/')
            && ! str_contains($image['path'], '..') && Storage::disk('local')->exists($image['path']);
    }

    /** @param array<string, mixed> $data @return list<array{string, string}> */
    private function slots(array $data): array
    {
        $slots = [];
        foreach (array_keys($data['figures'] ?? []) as $index) {
            $slots[] = ['figures.'.$index.'.image', 'figures.'.$index.'.image_id'];
        }
        foreach (app(ProgressReportData::class)->legacyFigureIndexes($data) as $index) {
            $slots[] = ['photo_'.$index, 'saved_photo_'.$index];
        }
        if (($data['report_type'] ?? 'progress') === 'terminal') {
            $slots[] = ['cover_image', 'saved_cover_image'];
        }

        return $slots;
    }
}
