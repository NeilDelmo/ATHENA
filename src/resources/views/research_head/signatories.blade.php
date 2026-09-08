<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold dark:text-white">Proposal signatory directory</h2><p class="mt-1 text-sm text-gray-500">Maintain the names and positions faculty can select for signature blocks.</p></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 py-6 sm:px-6">
        @if(session('success'))<p role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>@endif
        @if($errors->any())<div role="alert" class="text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="font-semibold dark:text-white">Add a signatory</h3>
            <form action="{{ route('signatories.store') }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                <input type="hidden" name="active" value="1">
                <label class="text-sm dark:text-gray-200">Signature role<select name="role_key" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800">@foreach($roles as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm dark:text-gray-200">Full name<input name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800"></label>
                <label class="text-sm dark:text-gray-200">Position / designation<input name="position" value="{{ old('position') }}" required maxlength="120" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800"></label>
                <div class="flex items-end"><button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Add name</button></div>
            </form>
        </section>
        <div class="space-y-3">
            @forelse($signatories as $signatory)
                <details class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <summary class="cursor-pointer text-sm dark:text-white"><strong>{{ $signatory->name }}</strong> · {{ $roles[$signatory->role_key] }} · {{ $signatory->active ? 'Active' : 'Inactive' }}</summary>
                    <form action="{{ route('signatories.update', $signatory) }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('PATCH')
                        <input type="hidden" name="role_key" value="{{ $signatory->role_key }}">
                        <label class="text-sm dark:text-gray-200">Name<input name="name" value="{{ $signatory->name }}" required maxlength="120" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800"></label>
                        <label class="text-sm dark:text-gray-200">Position<input name="position" value="{{ $signatory->position }}" required maxlength="120" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800"></label>
                        <label class="text-sm dark:text-gray-200">Availability<select name="active" class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-gray-800"><option value="1" @selected($signatory->active)>Active</option><option value="0" @selected(! $signatory->active)>Inactive — hide from new selections</option></select></label>
                        <div class="flex items-end"><button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Save changes</button></div>
                    </form>
                </details>
            @empty<p class="text-sm text-gray-500">No names yet. Add a name for each signature role above.</p>@endforelse
        </div>
    </div>
</x-app-layout>
