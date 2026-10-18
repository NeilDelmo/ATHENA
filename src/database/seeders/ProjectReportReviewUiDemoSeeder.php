<?php

namespace Database\Seeders;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProjectReportReviewUiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample report reviews may not be changed in production.');
        }

        DB::transaction(function (): void {
            $reports = collect();
            foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
                $reports = $reports->concat($model::query()->with('topic.user')
                    ->whereHas('topic', fn (Builder $query): Builder => $query->where('description', 'like', '[lifecycle-demo:%'))
                    ->when($model === ProjectNarrativeReport::class, fn (Builder $query): Builder => $query->where('report_type', 'progress'))
                    ->lockForUpdate()->get()->filter(fn ($report): bool => str_starts_with((string) $report->tracking_number, 'LIFE-'.$report->topic_id.'-')));
            }
            $updates = $reports->whereNull('review_stage');
            if ($updates->isEmpty()) {
                return;
            }
            foreach ($updates as $report) {
                if (blank($report->topic->user->college)) {
                    throw new RuntimeException('Set the sample Faculty Researcher college before updating report reviews.');
                }
                if (filled($report->official_pdf_path) && ! Storage::disk('local')->exists($report->official_pdf_path)) {
                    throw new RuntimeException('The sample report PDF is missing: '.$report->official_pdf_path);
                }
            }

            $backup = 'post-approval-ui-demo/before-secretary-review.json';
            if (! Storage::disk('local')->exists($backup) && ! Storage::disk('local')->put($backup,
                $updates->map(fn ($report): array => ['type' => $report instanceof ProjectProgressReport ? 'monitoring' : 'progress',
                    'attributes' => $report->getAttributes()])->values()->toJson(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Unable to back up the original sample report reviews.');
            }

            $head = User::role('research_head')->orderBy('id')->firstOrFail();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $role = Role::findOrCreate(User::WORKSPACE_RESEARCH_SECRETARY, 'web');
            $secretaries = [];
            foreach ($reports->groupBy(fn ($report): string => $report::class.':'.$report->topic_id) as $group) {
                $latest = $group->where('submission_status', 'submitted')->sortByDesc(fn ($report): string => ($report->reporting_date ?? $report->submission_date ?? $report->created_at)->format('Y-m-d').sprintf('-%010d', $report->id))->first();
                foreach ($group->whereNull('review_stage') as $report) {
                    $college = $report->topic->user->college;
                    if (! isset($secretaries[$college])) {
                        $secretary = User::firstOrCreate(['email' => 'sample-research-secretary-'.substr(hash('sha256', $college), 0, 12).'@example.test'], [
                            'name' => 'Sample Research Office Secretary', 'college' => $college, 'password' => Str::random(64),
                        ]);
                        if ($secretary->name !== 'Sample Research Office Secretary' || $secretary->college !== $college) {
                            throw new RuntimeException('The sample secretary identity is already used by another account.');
                        }
                        $secretary->assignRole($role);
                        $secretaries[$college] = $secretary;
                    }
                    $description = (string) $report->topic->description;
                    $isLatest = $latest?->is($report) === true;
                    $status = $report->isPrepared() ? 'pending' : $report->review_status;
                    $readyForHead = $isLatest && str_starts_with($description, '[lifecycle-demo:delayed-second]');
                    if ($isLatest && str_starts_with($description, '[lifecycle-demo:ongoing]')) {
                        $status = 'pending';
                    } elseif ($isLatest && str_starts_with($description, '[lifecycle-demo:delayed]')) {
                        $status = 'revision_requested';
                    } elseif ($readyForHead) {
                        $status = 'pending';
                    }
                    $forwarded = $report->isSubmitted() && ($status === 'reviewed' || $readyForHead);
                    $checked = $forwarded || $report->isSubmitted() && $status === 'revision_requested';
                    $checkedAt = ($report->submitted_at ?? $report->created_at)->copy()->addHour();
                    $accepted = $forwarded && $status === 'reviewed';
                    $report->update([
                        'review_stage' => $forwarded ? 'head' : 'secretary', 'review_status' => $status,
                        'secretary_remarks' => $checked ? ($forwarded
                            ? 'UI DEMONSTRATION ONLY: Research Office Secretary checked the report and supporting records before forwarding to the Research Head.'
                            : 'UI DEMONSTRATION ONLY: Research Office Secretary requests revised recruitment and recovery dates, variance explanations, and supporting evidence from the Faculty Researcher before forwarding.') : null,
                        'secretary_reviewed_by' => $checked ? $secretaries[$college]->id : null,
                        'secretary_reviewed_at' => $checked ? $checkedAt : null, 'forwarded_at' => $forwarded ? $checkedAt : null,
                        'research_head_remarks' => $accepted ? $report->research_head_remarks : null,
                        'reviewed_by' => $accepted ? ($report->reviewed_by ?? $head->id) : null,
                        'reviewed_at' => $accepted ? ($report->reviewed_at?->max($checkedAt->copy()->addHour()) ?? $checkedAt->copy()->addHour()) : null,
                    ]);
                }
            }
            $this->command?->info('Updated '.$updates->count().' existing Monitoring Tool and Progress Report samples. Original files were preserved.');
        });
    }
}
