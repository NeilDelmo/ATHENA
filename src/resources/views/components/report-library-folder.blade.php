@props(['href', 'name', 'description' => null])

<a href="{{ $href }}" title="{{ $description ? $name.' · '.$description : $name }}" {{ $attributes->merge(['class' => 'report-library-item']) }}>
    <x-report-library-icon name="folder" class="report-library-graphic" />
    <span class="min-w-0">
        <span class="report-library-name">{{ $name }}</span>
        @if ($description)<span class="report-library-meta">{{ $description }}</span>@endif
    </span>
</a>
