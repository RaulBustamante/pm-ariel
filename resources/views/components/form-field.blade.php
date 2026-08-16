@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'help' => null])

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
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>

    <input type="{{ $type }}"
           id="{{ $id }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if ($errors->has($name)) aria-invalid="true" @endif
           {{ $attributes->merge([
                'class' => 'block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600 sm:text-sm',
           ]) }}>

    @if ($help)
        <p id="{{ $id }}-help" class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @error($name)
        {{-- role="alert" para que un lector de pantalla lo anuncie al aparecer --}}
        <p id="{{ $id }}-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
