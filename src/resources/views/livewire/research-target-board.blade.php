<section class="dashboard-panel min-w-0 overflow-hidden {{ $compact ? '' : 'research-target-board' }}" aria-labelledby="research-targets-heading" data-research-target-board @if ($compact) data-dashboard-target-summary @endif>
    <div class="dashboard-panel-heading {{ $compact ? '' : '!gap-3 !p-4 lg:!flex-nowrap' }}">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex {{ $compact ? 'h-11 w-11' : 'h-10 w-10' }} shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand dark:bg-rose-950/40 dark:text-rose-300" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" /></svg></span>
            <div class="min-w-0">
                <h2 id="research-targets-heading" class="text-lg font-bold text-slate-950 dark:text-white">Research targets</h2>
                @if (! $compact && ! $canManage)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $collegeName ?: 'Targets allocated to your college.' }}</p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-end {{ $compact ? 'gap-3' : 'w-full gap-2 lg:w-auto lg:shrink-0 lg:flex-nowrap' }}">
            <div class="{{ $compact ? '' : 'min-w-0 basis-full lg:flex-none' }}">
                <x-input-label for="research-target-year" :value="$compact ? 'Target year' : 'Year / academic year'" class="sr-only" />
                <select id="research-target-year" wire:model.live="year" @disabled($editingTargets) class="{{ $compact ? '' : 'w-full lg:max-w-[15rem]' }} min-h-11 rounded-lg border-slate-300 bg-white text-sm text-slate-900 focus:border-brand focus:ring-brand disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    @if (! $years->contains($year))
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endif
                    @foreach ($years as $availableYear)
                        <option value="{{ $availableYear }}">{{ $availableYear }}</option>
                    @endforeach
                </select>
            </div>
            @if ($canManage && $compact)
                <a wire:navigate href="{{ route('research_head.targets.index') }}" aria-label="Manage research targets" title="Manage research targets" class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/70">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M15 5.5 18.5 9M4 20l4.5-1L20 7.5a2.5 2.5 0 0 0-3.5-3.5L5 15.5 4 20Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
            @elseif ($canManage && ! $editingTargets)
                <button type="button" wire:click="editTargets" wire:loading.attr="disabled" class="dashboard-action !px-3 disabled:opacity-60"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m15 5 4 4M4 20l5-1L20 8a2.8 2.8 0 0 0-4-4L5 15l-1 5Z" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ $hasTarget ? 'Edit targets' : 'Set targets' }}</button>
                <x-secondary-button wire:click="editTargets(true)" wire:loading.attr="disabled" class="min-h-11 !gap-2 !rounded-lg !px-3 !text-sm !normal-case !tracking-normal"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>Add year</x-secondary-button>
            @endif
        </div>
    </div>

    @if (session('research_targets_saved'))
        <p role="status" class="border-b border-slate-100 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-slate-800 dark:text-emerald-300">{{ session('research_targets_saved') }}</p>
    @endif

    @if ($canManage && ! $compact && ! $editingTargets)
        @php
            $configuredGoals = collect($rows)->filter(fn (array $row): bool => $row['total'] !== null)->count();
            $allocatedColleges = collect($rows)->flatMap(fn (array $row): array => array_keys(array_filter($row['allocations'], fn ($value): bool => $value !== null)))->unique()->count();
        @endphp
        <dl data-target-plan-overview class="flex flex-wrap items-center gap-x-5 gap-y-1 border-b border-slate-200/70 bg-slate-50/70 px-4 py-2 dark:border-slate-800 dark:bg-slate-950/40">
            <div data-target-plan-stat="indicators" class="flex items-baseline gap-1.5">
                <dt class="order-2 text-xs text-slate-600 dark:text-slate-400">Indicators</dt>
                <dd class="text-sm font-semibold tabular-nums text-slate-950 dark:text-white">{{ count($indicators) }}</dd>
            </div>
            <div data-target-plan-stat="configured" class="flex items-baseline gap-1.5">
                <dt class="order-2 text-xs text-slate-600 dark:text-slate-400">Goals set</dt>
                <dd class="text-sm font-semibold tabular-nums text-brand dark:text-rose-300">{{ $configuredGoals }}</dd>
            </div>
            <div data-target-plan-stat="colleges" class="flex items-baseline gap-1.5">
                <dt class="order-2 text-xs text-slate-600 dark:text-slate-400">Colleges<span class="sr-only"> with allocations</span></dt>
                <dd class="text-sm font-semibold tabular-nums text-slate-950 dark:text-white">{{ $allocatedColleges }}</dd>
            </div>
        </dl>
    @endif

    @if ($compact && $canManage && $hasTarget)
        <dl class="grid gap-px border-t border-slate-100 bg-slate-100 sm:grid-cols-2 xl:grid-cols-3 dark:border-slate-800 dark:bg-slate-800">
            @foreach ($rows as $key => $row)
                <div wire:key="target-summary-{{ $year }}-{{ $key }}" data-target-indicator="{{ $key }}" class="flex min-w-0 items-center justify-between gap-4 bg-white px-5 py-4 dark:bg-slate-900">
                    <dt class="text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $row['label'] }}</dt>
                    <dd class="shrink-0 font-bold tabular-nums {{ $row['total'] !== null ? 'text-xl text-brand dark:text-rose-300' : 'text-sm text-slate-400 dark:text-slate-500' }}">{{ $row['total'] !== null ? ($row['unit'] === 'percent' ? $row['total'].'%' : number_format($row['total'])) : 'Not set' }}</dd>
                </div>
            @endforeach
        </dl>
    @elseif ($canManage && $editingTargets)
        <form wire:submit="saveTargets" class="flex flex-col gap-4 p-4">
            @if ($errors->any())
                <x-input-error :messages="$errors->all()" role="alert" />
            @endif
            <h3 class="text-sm font-bold text-slate-950 dark:text-white">Plan details</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="research-target-academic-year" value="Year / academic year" />
                    <x-text-input id="research-target-academic-year" type="text" wire:model="targetForm.academic_year" placeholder="e.g. 2026-2027" required class="mt-1 block min-h-11 w-full" />
                    <x-input-error :messages="$errors->get('targetForm.academic_year')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="research-target-starts-on" value="Year starts" />
                    <x-text-input id="research-target-starts-on" type="date" wire:model="targetForm.starts_on" required class="mt-1 block min-h-11 w-full" />
                    <x-input-error :messages="$errors->get('targetForm.starts_on')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="research-target-ends-on" value="Year ends" />
                    <x-text-input id="research-target-ends-on" type="date" wire:model="targetForm.ends_on" required class="mt-1 block min-h-11 w-full" />
                    <x-input-error :messages="$errors->get('targetForm.ends_on')" class="mt-2" />
                </div>
            </div>
            <p class="rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2 text-xs leading-5 text-slate-600 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-slate-300">Choose a college for each indicator. All allocations are saved together. Leave unassigned targets blank; use 0 for an intentional zero. Count allocations cannot exceed the overall target; percentages apply separately to each college.</p>
            <div class="flex flex-col gap-2">
                @foreach ($indicators as $key => $indicator)
                    <details wire:key="target-editor-{{ $key }}" x-data="{ selectedCollege: @js(array_key_first($colleges)) }" class="target-editor-section group rounded-xl border border-slate-200 dark:border-slate-700" @if ($errors->has('targetForm.indicators.'.$key.'.*')) open @endif>
                        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-950 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white dark:hover:bg-slate-800/60">
                            <span>{{ $indicator['label'] }}{{ $indicator['unit'] === 'percent' ? ' (%)' : '' }}</span>
                            <span class="flex shrink-0 items-center gap-3"><span class="hidden text-xs font-medium text-slate-500 sm:inline dark:text-slate-400">{{ $indicator['unit'] === 'percent' ? 'Percentage' : 'Count' }}</span><svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                        </summary>
                        <div class="grid items-start gap-3 border-t border-slate-100 p-3 dark:border-slate-800 lg:grid-cols-4">
                            <div>
                                <x-input-label :for="'research-target-'.$key.'-total'">
                                    <span class="sr-only">{{ $indicator['label'] }} — </span>Overall target{{ $indicator['unit'] === 'percent' ? ' (%)' : '' }}
                                </x-input-label>
                                <x-text-input :id="'research-target-'.$key.'-total'" type="number" wire:model="targetForm.indicators.{{ $key }}.total" min="0" :max="$indicator['unit'] === 'percent' ? 100 : 1000000" :step="$indicator['unit'] === 'percent' ? '0.01' : '1'" :inputmode="$indicator['unit'] === 'percent' ? 'decimal' : 'numeric'" placeholder="Not set" class="mt-1 block min-h-11 w-full" />
                                <x-input-error :messages="$errors->get('targetForm.indicators.'.$key.'.total')" class="mt-2" />
                            </div>
                            <div class="min-w-0 lg:col-span-2">
                                <x-input-label :for="'research-target-'.$key.'-college'"><span class="sr-only">{{ $indicator['label'] }} — </span>College</x-input-label>
                                <select id="research-target-{{ $key }}-college" x-model="selectedCollege" class="mt-1 block min-h-11 w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-brand focus:ring-brand dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @foreach ($colleges as $code => $name)
                                        <option value="{{ $code }}">{{ $code === 'LAB_SCHOOL' ? $name : $code.' — '.$name }}{{ $errors->has('targetForm.indicators.'.$key.'.allocations.'.$code) ? ' (check target)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                @foreach ($colleges as $code => $name)
                                    <div wire:key="target-allocation-{{ $key }}-{{ $code }}" x-show="selectedCollege === @js($code)" x-cloak>
                                        <x-input-label :for="'research-target-'.$key.'-'.$code">
                                            <span class="sr-only">{{ $indicator['label'] }} — {{ $name }} — </span>College target{{ $indicator['unit'] === 'percent' ? ' (%)' : '' }}
                                        </x-input-label>
                                        <x-text-input :id="'research-target-'.$key.'-'.$code" type="number" wire:model="targetForm.indicators.{{ $key }}.allocations.{{ $code }}" x-bind:disabled="selectedCollege !== '{{ $code }}'" min="0" :max="$indicator['unit'] === 'percent' ? 100 : 1000000" :step="$indicator['unit'] === 'percent' ? '0.01' : '1'" :inputmode="$indicator['unit'] === 'percent' ? 'decimal' : 'numeric'" placeholder="Not assigned" class="mt-1 block min-h-11 w-full" />
                                        <x-input-error :messages="$errors->get('targetForm.indicators.'.$key.'.allocations.'.$code)" class="mt-2" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-3 dark:border-slate-800">
                <button type="submit" wire:loading.attr="disabled" class="dashboard-action disabled:opacity-60">Save targets</button>
                <x-secondary-button wire:click="cancelEditing" wire:loading.attr="disabled" class="min-h-11 !rounded-lg !text-sm !normal-case !tracking-normal">Cancel</x-secondary-button>
                <span wire:loading wire:target="saveTargets" role="status" class="text-sm text-slate-600 dark:text-slate-400">Saving targets…</span>
            </div>
        </form>
    @elseif (! $canManage && ! $collegeCode)
        <p class="px-5 py-8 text-sm text-slate-600 dark:text-slate-400">Your account needs an assigned college before its research targets can be shown.</p>
    @elseif (! $hasTarget || $rows === [])
        @if ($compact)
            <p class="px-5 py-8 text-sm text-slate-600 dark:text-slate-400">{{ $canManage ? 'No research targets have been set for '.$year.'.' : 'No research targets have been allocated to your college for '.$year.'.' }}</p>
        @else
        <div class="flex flex-col items-center gap-2 px-4 py-6 text-center">
            <h3 class="text-base font-bold text-slate-950 dark:text-white">{{ $canManage ? 'A new year of research starts here' : 'Your college targets will appear here' }}</h3>
            <p class="max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $canManage ? 'No research targets have been set for '.$year.'.' : 'No research targets have been allocated to your college for '.$year.'.' }}</p>
            @if ($canManage && ! $compact)
                <button type="button" wire:click="editTargets" wire:loading.attr="disabled" class="dashboard-action mt-2 disabled:opacity-60">Start planning</button>
            @endif
        </div>
        @endif
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
            <h3 class="text-sm font-bold text-slate-950 dark:text-white">{{ $canManage ? 'Indicators & allocations' : 'Your college allocations' }}</h3>
            @if ($period)
                <p class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ \Illuminate\Support\Carbon::parse($period['starts_on'])->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($period['ends_on'])->format('M j, Y') }}</p>
            @endif
        </div>
        <div role="region" aria-label="Research targets for {{ $year }}" tabindex="0" class="focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand">
            <table class="research-target-table w-full text-left text-sm" @if ($canManage) data-target-manager-table @endif>
                <caption class="sr-only">Research targets for {{ $year }}{{ ! $canManage && $collegeName ? ' — '.$collegeName : '' }}</caption>
                <thead class="border-y border-slate-200/70 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Indicator</th>
                        @if ($canManage)
                            <th scope="col" class="whitespace-nowrap px-3 py-2 font-semibold">Overall target</th>
                            <th scope="col" class="px-4 py-2 font-semibold">College allocations</th>
                        @else
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Your college target</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($rows as $key => $row)
                        <tr wire:key="target-row-{{ $year }}-{{ $key }}" data-target-indicator="{{ $key }}" class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                            <th scope="row" class="target-indicator-label px-4 py-2 font-medium leading-5 text-slate-950 dark:text-white">{{ $row['label'] }}<span class="sr-only"> — {{ $row['unit'] === 'percent' ? 'Percentage target' : 'Count target' }}</span></th>
                            @if ($canManage)
                                <td class="target-overall-value px-3 py-2 tabular-nums"><span class="target-mobile-label">Overall target</span><span data-target-overall class="{{ $row['total'] !== null ? 'text-base font-semibold text-brand dark:text-rose-300' : 'text-sm text-slate-500 dark:text-slate-400' }}">{{ $row['total'] !== null ? ($row['unit'] === 'percent' ? $row['total'].'%' : number_format($row['total'])) : 'Not set' }}</span></td>
                                <td class="target-college-values px-4 py-2">
                                    <span class="target-mobile-label">College allocations</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse (array_filter($row['allocations'], fn ($allocation) => $allocation !== null) as $code => $allocation)
                                            <span title="{{ $colleges[$code] }}" class="inline-flex items-center overflow-hidden rounded-md border border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800/60"><span class="px-2 py-1 font-medium text-slate-600 dark:text-slate-300">{{ $code === 'LAB_SCHOOL' ? 'Lab School' : $code }}</span><span class="border-l border-slate-200 bg-slate-50 px-2 py-1 font-semibold tabular-nums text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ $row['unit'] === 'percent' ? $allocation.'%' : number_format($allocation) }}</span></span>
                                        @empty
                                            <span class="text-slate-500 dark:text-slate-400">Not assigned</span>
                                        @endforelse
                                    </div>
                                </td>
                            @else
                                <td class="px-4 py-2 text-right font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $row['value'] !== null ? ($row['unit'] === 'percent' ? $row['value'].'%' : number_format($row['value'])) : 'Not assigned' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-200/70 bg-slate-50/70 px-4 py-2 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">Not assigned means a target is unset. A displayed 0 is an intentional zero target.{{ $canManage ? ' Percentage targets apply separately to each college.' : '' }}</p>
    @endif
</section>
