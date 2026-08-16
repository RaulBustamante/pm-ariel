@props(['name', 'label', 'help' => null, 'term' => null, 'value' => 3])

@php
    $id = $name.'-field';
    $selected = (int) old($name, $value);
@endphp

<fieldset class="space-y-1.5">
    <legend class="text-sm font-medium text-slate-800">
        {{ $label }}
        @if ($term)
            <x-help-term :term="$term" />
        @endif
    </legend>

    @if ($help)
        <p id="{{ $id }}-help" class="text-xs leading-relaxed text-slate-600">{{ $help }}</p>
    @endif

    {{-- Botones de opción y no una lista desplegable: los cinco valores se ven a
         la vez, y elegir es un clic en vez de tres. --}}
    <div class="flex gap-1" role="radiogroup" @if ($help) aria-describedby="{{ $id }}-help" @endif>
        @foreach ([1, 2, 3, 4, 5] as $level)
            <label class="flex-1 cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $level }}"
                       @checked($selected === $level)
                       class="peer sr-only">
                <span class="block rounded-md border border-slate-300 py-1.5 text-center text-sm text-slate-700
                             peer-checked:border-blue-700 peer-checked:bg-blue-700 peer-checked:font-semibold peer-checked:text-white
                             peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600 peer-focus-visible:ring-offset-1">
                    {{ $level }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
