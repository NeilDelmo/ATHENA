@props(['title', 'columns', 'rows' => [], 'emptyMessage' => 'No records for this quarter.'])

<section {{ $attributes->class(['dashboard-panel overflow-hidden']) }}>
    <h3 class="border-b border-gray-100 px-5 py-4 text-base font-bold text-gray-950 dark:border-slate-800 dark:text-white">{{ $title }}</h3>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" class="px-5 py-3 align-top font-semibold">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="whitespace-pre-line px-5 py-4 align-top text-gray-700 [overflow-wrap:anywhere] dark:text-slate-300">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="px-5 py-6 text-slate-500 dark:text-slate-400">{{ $emptyMessage }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
