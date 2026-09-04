<div data-proposal-review-package>
    @if ($statusMessage !== '')
        <div role="status" class="mb-6 border-l-4 border-green-600 bg-green-50 px-5 py-4 text-sm text-green-950">
            <p class="font-black">PDF package prepared</p>
            <p class="mt-1">{{ $statusMessage }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-6 border-l-4 border-red-600 bg-red-50 px-5 py-4 text-sm text-red-950">
            <p class="font-black">This proposal package cannot be turned in yet.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        @include('faculty.proposal-drafts._review-package', ['inModal' => $inModal])
    </div>
</div>
