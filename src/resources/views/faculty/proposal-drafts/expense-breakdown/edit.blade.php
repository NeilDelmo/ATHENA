<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $expenseBreakdownDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $expenseBreakdownDocument?->completed_at ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Complete the official expense table through structured inputs. Totals and subtotals are calculated automatically.</p>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialData = array_replace($sourceData, old());
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="false"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}"
        x-data="proposalDraftExpenseBreakdown({
            initialData: @js($initialData),
            previewUrl: @js(route('faculty.proposal-drafts.expense-breakdown.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.expense-breakdown.download', $proposalDraft)),
            csrfToken: @js(csrf_token()),
            accountCatalog: @js(config('expense_breakdown.accounts')),
        })"
    >
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The Estimated Expense Breakdown could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        <x-paper-editor-submit-status />

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The shared project title and required project information must be complete before this paper can be saved or generated.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Shared project information</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500">The Project Title is taken from Project Details and printed above the official expense table.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View official sample</a>@endif
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 border-t border-gray-100 pt-5">
                <dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $proposalDraft->project_title ?: 'Not provided' }}</dd>
            </dl>
        </section>

        <form data-paper-form x-ref="form" x-on:submit="if (!validateForm()) $event.preventDefault()" action="{{ route('faculty.proposal-drafts.expense-breakdown.update', $proposalDraft) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ $expenseBreakdownDocument?->lock_version ?? 0 }}">

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">Expense items</h3>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Choose an account and sub-account from the official workbook, then enter its expense details. Matching rows are grouped with the prescribed subtotals.</p>
                    </div>
                    <button type="button" x-on:click="addItem(true)" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Add expense item</button>
                </div>

                <div class="mt-5 space-y-4">
                    <template x-for="(item, index) in items" :key="item.id">
                        <article class="rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wider text-gray-500">Expense item <span x-text="index + 1"></span></p>
                                    <p class="mt-1 text-sm font-black text-gray-900">Php <span x-text="formatMoney(itemTotal(item))"></span></p>
                                </div>
                                <button type="button" x-on:click="removeItem(index)" x-bind:disabled="items.length === 1" class="rounded-xl px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-category-${item.id}`">Expense type</label>
                                    <select :id="`expense-category-${item.id}`" :name="`items[${index}][category]`" x-model="item.category" x-on:change="$nextTick(() => syncGrouping(item, true))" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        @foreach (config('expense_breakdown.categories') as $categoryKey => $categoryLabel)
                                            <option value="{{ $categoryKey }}">{{ $categoryLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-account-${item.id}`">Account</label>
                                    <select :id="`expense-account-${item.id}`" :name="`items[${index}][account]`" x-model="item.account" x-on:change="$nextTick(() => syncGrouping(item))" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        <option value="">Select an official account</option>
                                        <template x-for="account in accountsFor(item)" :key="account.label">
                                            <option :value="account.label" x-text="account.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-sub-account-${item.id}`">Sub-account</label>
                                    <select :id="`expense-sub-account-${item.id}`" :name="`items[${index}][sub_account]`" x-model="item.sub_account" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        <option value="">Select an official sub-account</option>
                                        <template x-for="subAccount in subAccountsFor(item)" :key="subAccount.label">
                                            <option :value="subAccount.label" x-text="subAccount.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <template x-if="!isContingency(item)">
                                <div>
                                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_8rem_8rem_11rem]">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-particulars-${item.id}`">Particular/s</label>
                                            <input :id="`expense-particulars-${item.id}`" :name="`items[${index}][particulars]`" type="text" maxlength="255" x-model="item.particulars" required placeholder="e.g. Prepaid Card" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-${item.id}`">Unit</label>
                                            <input :id="`expense-unit-${item.id}`" :name="`items[${index}][unit]`" type="text" maxlength="50" x-model="item.unit" required placeholder="pc, hours" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-quantity-${item.id}`">Qty.</label>
                                            <input :id="`expense-quantity-${item.id}`" :name="`items[${index}][quantity]`" type="number" min="0.01" max="{{ config('expense_breakdown.maximum_quantity') }}" step="0.01" x-model="item.quantity" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-cost-${item.id}`">Unit Cost (Php)</label>
                                            <input :id="`expense-unit-cost-${item.id}`" :name="`items[${index}][unit_cost]`" type="number" min="0.01" max="{{ config('expense_breakdown.maximum_unit_cost') }}" step="0.01" x-model="item.unit_cost" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-details-${item.id}`">Descriptions / Specifications / Details</label>
                                            <textarea :id="`expense-details-${item.id}`" :name="`items[${index}][details]`" rows="3" maxlength="500" x-model="item.details" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-purpose-${item.id}`">Purpose in the project</label>
                                            <textarea :id="`expense-purpose-${item.id}`" :name="`items[${index}][purpose]`" rows="3" maxlength="500" x-model="item.purpose" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="isContingency(item)">
                                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
                                    <input type="hidden" :name="`items[${index}][particulars]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][details]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][unit]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][quantity]`" value="1">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-purpose-${item.id}`">Purpose in the project</label>
                                        <textarea :id="`expense-purpose-${item.id}`" :name="`items[${index}][purpose]`" rows="3" maxlength="500" x-model="item.purpose" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-cost-${item.id}`">Contingency amount (Php)</label>
                                        <input :id="`expense-unit-cost-${item.id}`" :name="`items[${index}][unit_cost]`" type="number" min="0.01" max="{{ config('expense_breakdown.maximum_unit_cost') }}" step="0.01" x-model="item.unit_cost" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    </div>
                                </div>
                            </template>
                        </article>
                    </template>
                </div>

                <button type="button" x-on:click="addItem(true)" class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">Add another expense item</button>
            </section>

            <section class="rounded-2xl border border-gray-900 bg-gray-900 p-5 text-white shadow-sm sm:p-6">
                <p class="text-xs font-black uppercase tracking-wider text-gray-300">Total MOOE and Capital Outlay</p>
                <p class="mt-1 text-3xl font-black">Php <span x-text="formatMoney(grandTotal())"></span></p>
                <div class="mt-4 grid gap-3 border-t border-gray-700 pt-4 text-sm sm:grid-cols-2">
                    <p>MOOE: <strong>Php <span x-text="formatMoney(categoryTotal('mooe'))"></span></strong></p>
                    <p>Capital Outlay: <strong>Php <span x-text="formatMoney(categoryTotal('capital_outlay'))"></span></strong></p>
                </div>
            </section>

            @include('faculty.proposal-drafts.partials.change-note')

            <div class="flex flex-col-reverse gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <a data-paper-discard href="{{ route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Discard changes</a>
                <a data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel and exit</a>
                <button type="button" x-on:click="generatePreview" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!previewLoading">Preview paper</span><span x-show="previewLoading" x-cloak>Generating&hellip;</span></button>
                <button type="button" x-on:click="downloadDocument" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!downloadLoading">Download Excel file</span><span x-show="downloadLoading" x-cloak>Preparing&hellip;</span></button>
                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save and exit</button>
                <button data-paper-save type="submit" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save changes</button>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 class="text-base font-black text-gray-900">Estimated Expense Breakdown preview</h3><p class="mt-1 text-xs text-gray-500">This follows the supplied official table and automatically inserts group subtotals.</p></div>
                <button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 disabled:opacity-50 sm:w-auto">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Estimated Expense Breakdown preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
