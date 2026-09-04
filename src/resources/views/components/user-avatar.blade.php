@props(['user'])

@php
    $initial = Str::of($user->name)->trim()->substr(0, 1)->upper()->value() ?: '?';
@endphp

<div
    x-data="{ avatarFailed: false }"
    @if (filled($user->avatar))
        x-init="$nextTick(() => { if ($refs.image.complete && $refs.image.naturalWidth === 0) avatarFailed = true })"
    @endif
    role="img"
    aria-label="Profile photo for {{ $user->name }}"
    data-user-avatar
    {{ $attributes->class(['relative inline-flex shrink-0 items-center justify-center overflow-hidden font-black uppercase text-white']) }}
>
    <span aria-hidden="true" class="flex h-full w-full items-center justify-center">{{ $initial }}</span>

    @if (filled($user->avatar))
        <img
            x-ref="image"
            x-show="! avatarFailed"
            x-on:error="avatarFailed = true"
            src="{{ $user->avatar }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            data-user-avatar-image
        >
    @endif
</div>