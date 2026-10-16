<?php

namespace App\Livewire;

use App\Models\ResearchAnnualTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResearchTargetBoard extends Component
{
    public string $year = '';

    public array $targetForm = [];

    #[Locked]
    public bool $compact = false;

    #[Locked]
    public bool $editingTargets = false;

    #[Locked]
    public ?int $editingTargetId = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isUsingWorkspace([
            User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_RESEARCH_SECRETARY, User::WORKSPACE_RESEARCH_OFFICE,
        ]), 403);
    }

    public function mount(): void
    {
        $targets = ResearchAnnualTarget::orderByDesc('starts_on')->get(['academic_year', 'starts_on', 'ends_on']);
        $current = $targets->first(fn (ResearchAnnualTarget $target): bool => now()->betweenIncluded($target->starts_on, $target->ends_on->endOfDay()));
        $this->year = $current?->academic_year ?? $targets->first()?->academic_year ?? now()->format('Y');
    }

    public function updatedYear(): void
    {
        $this->validate(['year' => ['required', 'string', 'max:50']]);
        $this->reset('targetForm', 'editingTargets', 'editingTargetId');
        $this->resetValidation();
    }

    public function editTargets(bool $new = false): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetValidation();
        $target = $new ? null : $this->selectedTarget();
        $this->editingTargetId = $target?->id;
        $this->targetForm = [
            'academic_year' => $target?->academic_year ?? ($new ? '' : $this->year),
            'starts_on' => $target?->starts_on->toDateString() ?? now()->startOfYear()->toDateString(),
            'ends_on' => $target?->ends_on->toDateString() ?? now()->endOfYear()->toDateString(),
            'indicators' => [],
        ];

        foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
            $entry = $target?->indicators[$key] ?? [];
            $this->targetForm['indicators'][$key] = [
                'total' => ($key === 'publications' ? $target?->publications_target : ($entry['total'] ?? null)) ?? '',
                'allocations' => array_replace(array_fill_keys(array_keys($this->colleges()), ''), $entry['allocations'] ?? []),
            ];
        }

        $this->editingTargets = true;
    }

    public function cancelEditing(): void
    {
        abort_unless($this->canManage(), 403);
        $this->reset('targetForm', 'editingTargets', 'editingTargetId');
        $this->resetValidation();
    }

    public function saveTargets(): void
    {
        abort_unless($this->canManage(), 403);
        if (is_string($this->targetForm['academic_year'] ?? null)) {
            $this->targetForm['academic_year'] = trim($this->targetForm['academic_year']);
        }

        $indicatorKeys = implode(',', array_keys(ResearchAnnualTarget::INDICATORS));
        $collegeKeys = implode(',', array_keys($this->colleges()));
        $rules = [
            'targetForm' => ['required', 'array:academic_year,starts_on,ends_on,indicators'],
            'targetForm.academic_year' => ['required', 'string', 'max:50', Rule::unique(ResearchAnnualTarget::class, 'academic_year')->ignore($this->editingTargetId)],
            'targetForm.starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:2101-01-01'],
            'targetForm.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:targetForm.starts_on', 'before:2101-01-01'],
            'targetForm.indicators' => ['required', 'array:'.$indicatorKeys, 'required_array_keys:'.$indicatorKeys],
        ];
        $attributes = [];

        foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
            $path = 'targetForm.indicators.'.$key;
            $numberRules = $indicator['unit'] === 'percent'
                ? ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100']
                : ['nullable', 'integer', 'min:0', 'max:1000000'];
            $rules[$path] = ['required', 'array:total,allocations', 'required_array_keys:total,allocations'];
            $rules[$path.'.total'] = $numberRules;
            $rules[$path.'.allocations'] = ['present', 'array:'.$collegeKeys];
            $rules[$path.'.allocations.*'] = $numberRules;
            $attributes[$path.'.total'] = $indicator['label'].' overall target';
            foreach ($this->colleges() as $code => $college) {
                $attributes[$path.'.allocations.'.$code] = $indicator['label'].' allocation for '.$college;
            }
        }

        $validated = Validator::make(['targetForm' => $this->targetForm], $rules, [], $attributes)
            ->after(function (ValidationValidator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->targetForm['ends_on'] > CarbonImmutable::parse($this->targetForm['starts_on'])->addYears(2)->toDateString()) {
                    $validator->errors()->add('targetForm.ends_on', 'Choose a research target period of two years or less.');
                }

                foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
                    $entry = $this->targetForm['indicators'][$key];
                    $allocations = array_filter($entry['allocations'], fn (mixed $value): bool => $value !== null && $value !== '');
                    if ($allocations !== [] && ($entry['total'] === null || $entry['total'] === '')) {
                        $validator->errors()->add('targetForm.indicators.'.$key.'.total', 'Set the overall target before assigning college allocations.');
                    } elseif ($indicator['unit'] === 'count' && array_sum($allocations) > (int) $entry['total']) {
                        $validator->errors()->add('targetForm.indicators.'.$key.'.total', 'College allocations cannot exceed the overall target.');
                    }
                }
            })->validate()['targetForm'];

        foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
            $normalize = fn (mixed $value): int|float|null => $value === null || $value === ''
                ? null : ($indicator['unit'] === 'percent' ? (float) $value : (int) $value);
            $validated['indicators'][$key]['total'] = $normalize($validated['indicators'][$key]['total']);
            $validated['indicators'][$key]['allocations'] = array_filter(
                array_map($normalize, $validated['indicators'][$key]['allocations']),
                fn (mixed $value): bool => $value !== null,
            );
        }

        $validated['publications_target'] = $validated['indicators']['publications']['total'];
        unset($validated['indicators']['publications']['total']);
        $target = $this->editingTargetId !== null
            ? ResearchAnnualTarget::findOrFail($this->editingTargetId)
            : new ResearchAnnualTarget;
        $target->fill([...$validated, 'updated_by' => auth()->id()])->save();
        $this->year = $target->academic_year;
        $this->cancelEditing();
        session()->flash('research_targets_saved', 'Research targets saved.');
    }

    private function canManage(): bool
    {
        return auth()->user()->isUsingWorkspace([User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_RESEARCH_SECRETARY]);
    }

    /** @return array<string, string> */
    private function colleges(): array
    {
        return [...User::COLLEGES, 'LAB_SCHOOL' => 'Laboratory School'];
    }

    private function selectedTarget(): ?ResearchAnnualTarget
    {
        return ResearchAnnualTarget::where('academic_year', $this->year)->first();
    }

    public function render(): View
    {
        $canManage = $this->canManage();
        if (! $canManage) {
            $this->reset('targetForm', 'editingTargets', 'editingTargetId');
        }

        $target = $this->selectedTarget();
        $collegeCode = array_search(auth()->user()->college, User::COLLEGES, true) ?: null;
        $rows = [];

        foreach (ResearchAnnualTarget::INDICATORS as $key => $indicator) {
            $entry = $target?->indicators[$key] ?? [];
            if ($canManage) {
                $rows[$key] = [
                    'indicator' => $key, ...$indicator,
                    'total' => $key === 'publications' ? $target?->publications_target : ($entry['total'] ?? null),
                    'allocations' => array_intersect_key($entry['allocations'] ?? [], $this->colleges()),
                ];
            } elseif ($collegeCode !== null && isset($entry['allocations'][$collegeCode])) {
                $rows[$key] = ['indicator' => $key, ...$indicator, 'value' => $entry['allocations'][$collegeCode]];
            }
        }

        return view('livewire.research-target-board', [
            'canManage' => $canManage, 'collegeCode' => $collegeCode, 'collegeName' => auth()->user()->college,
            'colleges' => $this->colleges(), 'indicators' => ResearchAnnualTarget::INDICATORS, 'rows' => $rows,
            'years' => ResearchAnnualTarget::orderByDesc('starts_on')->pluck('academic_year')->prepend($this->year)->unique()->values(),
            'period' => $target ? ['starts_on' => $target->starts_on->toDateString(), 'ends_on' => $target->ends_on->toDateString()] : null,
            'hasTarget' => $target !== null,
        ]);
    }
}
