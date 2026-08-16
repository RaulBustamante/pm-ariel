@props(['active' => false])

<a {{ $attributes->merge([
        'class' => 'rounded px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-600 '
            . ($active
                ? 'bg-slate-100 text-slate-900'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'),
   ]) }}
   @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
