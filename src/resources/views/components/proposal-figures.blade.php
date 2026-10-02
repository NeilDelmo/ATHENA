@props(['proposal', 'section'])
@foreach ($proposal['methodology_images'] as $image)
    @continue($image['section'] !== $section)
    <p class="detailed-proposal-methodology-visual is-{{ $image['alignment'] }} is-{{ $image['size'] }}"><img src="{{ $image['data_url'] }}" alt="{{ $image['caption'] ?: 'Proposal figure' }}"></p>
    <p class="detailed-proposal-methodology-caption is-{{ $image['alignment'] }}">Figure {{ $image['figure_number'] }}. {{ $image['caption'] }}</p>
@endforeach
