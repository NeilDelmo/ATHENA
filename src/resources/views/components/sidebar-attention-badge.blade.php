@props(['count' => 0])

@if ($count > 0)
    <span
        x-show="sidebarOpen"
        class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white dark:bg-red-500"
        aria-label="{{ $count }} unread {{ \Illuminate\Support\Str::plural('update', $count) }}"
    >
        {{ $count > 99 ? '99+' : $count }}
    </span>
    <span
        x-show="!sidebarOpen"
        class="absolute right-0.5 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full bg-red-600 ring-2 ring-white dark:bg-red-500 dark:ring-slate-950"
        aria-label="{{ $count }} unread {{ \Illuminate\Support\Str::plural('update', $count) }}"
    ></span>
@endif
