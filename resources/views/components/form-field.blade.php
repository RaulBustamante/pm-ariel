@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'help' => null, 'autofocus' => false])

@php
    $id = $name . '-field';
    $describedBy = collect([
        $help ? $id . '-help' : null,
        $errors->has($name) ? $id . '-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="space-y-1">
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-[var(--color-badge-danger-fg)]" aria-hidden="true">*</span>
        @endif
    </label>

    <input type="{{ $type }}"
           id="{{ $id }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($autofocus) autofocus @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if ($errors->has($name)) aria-invalid="true" @endif
           {{ $attributes->merge([
                'class' => 'field',
           ]) }}>

    @if ($help)
        <p id="{{ $id }}-help" class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @error($name)
        {{-- role="alert" para que un lector de pantalla lo anuncie al aparecer --}}
        <p id="{{ $id }}-error" role="alert" class="text-sm text-[var(--color-badge-danger-fg)]">{{ $message }}</p>
    @enderror
</div>
