@props(['href', 'active' => false, 'icon' => null])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->merge(['class' => 'nav-item']) }}>
    @if ($icon)
        <x-icon :name="$icon" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
