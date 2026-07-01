<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900">Account Profile</h2>
            <p class="mt-1 text-xs text-gray-500">Your institutional identity and ATHENA access.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-6">
                <div class="flex items-center gap-4">
                    @if ($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-14 w-14 rounded-2xl border border-gray-200 object-cover">
                    @else
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-600 text-lg font-black uppercase text-white">{{ substr($user->name, 0, 1) }}</div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="truncate text-lg font-black text-gray-900">{{ $user->name }}</h3>
                        <p class="mt-0.5 truncate text-sm text-gray-500">{{ $user->email }}</p>
                    </div>
                </div>
            </div>

            <dl class="divide-y divide-gray-100">
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[180px_1fr] sm:items-center">
                    <dt class="text-xs font-bold uppercase tracking-wider text-gray-400">Authentication</dt>
                    <dd class="text-sm font-bold text-gray-700">BatStateU Google Workspace</dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[180px_1fr] sm:items-center">
                    <dt class="text-xs font-bold uppercase tracking-wider text-gray-400">ATHENA role</dt>
                    <dd class="flex flex-wrap gap-2">@forelse ($user->getRoleNames() as $role)<span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-700">{{ str_replace('_', ' ', $role) }}</span>@empty<span class="text-sm text-gray-500">No role assigned</span>@endforelse</dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[180px_1fr] sm:items-center">
                    <dt class="text-xs font-bold uppercase tracking-wider text-gray-400">Account management</dt>
                    <dd class="text-sm leading-6 text-gray-600">Your name, email, password, and account recovery are managed by Batangas State University. Changes made to your Google profile are synchronized the next time you sign in.</dd>
                </div>
            </dl>
        </section>
    </div>
</x-app-layout>
