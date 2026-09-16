@props(['tables' => []])
@php($input = 'mt-2 block min-h-12 w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-base text-gray-950 shadow-sm transition placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white')

<section
    id="terminal-tables"
    data-terminal-table-builder
    class="scroll-mt-24 space-y-5"
    x-data="{ tables: @js($tables) }"
    aria-labelledby="terminal-tables-heading"
>
    <div class="flex flex-col gap-4 border-b-2 border-gray-950 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-white">
        <div>
            <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Structured evidence</p>
            <h3 id="terminal-tables-heading" class="font-serif text-2xl font-bold text-gray-950 dark:text-white">Research tables</h3>
            <p class="mt-2 max-w-3xl text-gray-600 dark:text-slate-300">Create a table for methods, measurements, comparisons, or findings. Position 0 places it at the end of the selected section.</p>
        </div>
        <button
            type="button"
            @click="tables.push({caption:'',section:'results_discussion',after_paragraph:0,headers:['',''],rows:[['','']]}); $dispatch('input')"
            :disabled="tables.length >= 30"
            class="min-h-12 shrink-0 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-40"
        >▦ Create table</button>
    </div>

    <div x-show="tables.length === 0" class="rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-800/50">
        <p class="font-serif text-xl font-bold text-gray-950 dark:text-white">No tables added yet</p>
        <p class="mt-2 text-gray-600 dark:text-slate-300">Choose “Create table” to start with two columns and one row.</p>
    </div>

    <template x-for="(table, ti) in tables" :key="ti">
        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <header class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:bg-slate-800">
                <h4 class="font-serif text-xl font-bold text-gray-950 dark:text-white" x-text="'Table ' + (ti + 1)"></h4>
                <button type="button" @click="tables.splice(ti, 1); $dispatch('input')" class="min-h-11 rounded-xl border border-red-200 bg-white px-4 py-2 font-bold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Remove table</button>
            </header>

            <div class="space-y-5 p-5">
                <label class="block font-bold">Table caption<input :name="`terminal_data[tables][${ti}][caption]`" x-model="table.caption" required maxlength="300" class="{{ $input }}" placeholder="Describe the comparison or findings shown"></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="font-bold">Insert in section<select :name="`terminal_data[tables][${ti}][section]`" x-model="table.section" class="{{ $input }}"><option value="methodology">Methodology</option><option value="results_discussion">Results and Discussion</option></select></label>
                    <label class="font-bold">After paragraph<input type="number" min="0" max="1000" :name="`terminal_data[tables][${ti}][after_paragraph]`" x-model="table.after_paragraph" class="{{ $input }}"><span class="mt-1 block font-normal text-gray-500">Use 0 to place it at the section end.</span></label>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-300 dark:border-slate-600">
                    <table class="min-w-[680px] w-full border-collapse">
                        <thead class="bg-gray-950 text-white">
                            <tr>
                                <template x-for="(heading, ci) in table.headers" :key="ci">
                                    <th scope="col" class="min-w-48 border-r border-white/20 p-3 text-left align-top last:border-r-0">
                                        <label class="block font-bold">
                                            <span x-text="'Column ' + (ci + 1) + ' heading'"></span>
                                            <input :aria-label="'Column ' + (ci + 1) + ' heading'" :name="`terminal_data[tables][${ti}][headers][${ci}]`" x-model="table.headers[ci]" required maxlength="300" class="mt-2 block min-h-11 w-full rounded-lg border-white/30 bg-white px-3 py-2 text-base text-gray-950 focus:border-red-400 focus:ring-red-400">
                                        </label>
                                    </th>
                                </template>
                                <th scope="col" class="w-32 p-3 text-left">Row actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <template x-for="(row, ri) in table.rows" :key="ri">
                                <tr class="align-top">
                                    <template x-for="(heading, ci) in table.headers" :key="ci">
                                        <td class="border-r border-gray-200 p-3 last:border-r-0 dark:border-slate-700">
                                            <label class="sr-only" x-text="'Row ' + (ri + 1) + ', ' + (heading || ('column ' + (ci + 1)))"></label>
                                            <textarea :aria-label="'Row ' + (ri + 1) + ', column ' + (ci + 1)" :name="`terminal_data[tables][${ti}][rows][${ri}][${ci}]`" x-model="table.rows[ri][ci]" maxlength="5000" rows="3" class="{{ $input }}"></textarea>
                                        </td>
                                    </template>
                                    <td class="p-3">
                                        <button type="button" @click="table.rows.splice(ri, 1); $dispatch('input')" :disabled="table.rows.length === 1" class="min-h-11 rounded-lg px-3 py-2 font-bold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-300">Remove row</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="table.headers.push(''); table.rows.forEach(row => row.push('')); $dispatch('input')" :disabled="table.headers.length >= 8" class="min-h-11 rounded-xl border border-gray-300 px-4 py-2 font-bold text-gray-900 transition hover:bg-gray-50 disabled:opacity-40 dark:border-slate-600 dark:text-white dark:hover:bg-slate-800">＋ Add column</button>
                    <button type="button" @click="table.headers.pop(); table.rows.forEach(row => row.pop()); $dispatch('input')" :disabled="table.headers.length <= 2" class="min-h-11 rounded-xl border border-gray-300 px-4 py-2 font-bold text-gray-900 transition hover:bg-gray-50 disabled:opacity-40 dark:border-slate-600 dark:text-white dark:hover:bg-slate-800">Remove last column</button>
                    <button type="button" @click="table.rows.push(table.headers.map(() => '')); $dispatch('input')" :disabled="table.rows.length >= 200" class="min-h-11 rounded-xl bg-gray-950 px-4 py-2 font-bold text-white transition hover:bg-red-800 disabled:opacity-40">＋ Add row</button>
                </div>
            </div>
        </article>
    </template>
</section>
