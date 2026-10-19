@props(['proposal', 'section'])
@foreach ($proposal['methodology_images'] as $image)
    @continue($image['section'] !== $section)
    <p class="detailed-proposal-methodology-visual is-{{ $image['alignment'] }} is-{{ $image['size'] }}"><picture>@if(isset($image['original_url']))<source media="print" srcset="{{ $image['original_url'] }}">@endif<img src="{{ $image['data_url'] }}" alt="{{ $image['caption'] ?: 'Proposal figure' }}" loading="lazy" decoding="async"></picture></p>
    <p class="detailed-proposal-methodology-caption is-{{ $image['alignment'] }}">Figure {{ $image['figure_number'] }}. {{ $image['caption'] }}</p>
@endforeach
