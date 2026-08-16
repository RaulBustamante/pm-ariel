@props([
    'name',
    'charter',
    'project',
    'step',
    'rows' => 3,
    'term' => null,
    'example' => null,
    'canSuggest' => false,
])

@php
    $id = $name.'-field';
    $label = __("initiation.field_{$name}");
    $help = __("initiation.help_{$name}");
    $value = old($name, $charter->{$name});

    // Cuando se acaba de proponer un borrador para ESTE campo, se marca para que
    // el usuario vea de inmediato cuál cambió y que todavía no está guardado.
    $justSuggested = session('suggested_field') === $name;
@endphp

<div class="space-y-1.5">
    <div class="flex items-start justify-between gap-3">
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-800">
            {{ $label }}
            @if ($term)
                <x-help-term :term="$term" />
            @endif
        </label>

        @if ($canSuggest)
            {{-- Un formulario aparte: el botón de sugerir no debe arrastrar ni
                 guardar lo que el usuario lleva escrito en los demás campos. --}}
            <button type="submit"
                    form="suggest-{{ $name }}"
                    class="shrink-0 rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:border-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('initiation.suggest') }}
            </button>
        @endif
    </div>

    <p class="text-xs leading-relaxed text-slate-600">{{ $help }}</p>

    <textarea id="{{ $id }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
              class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600
                     {{ $justSuggested ? 'border-blue-400 bg-blue-50/40' : '' }}">{{ $value }}</textarea>

    @if ($justSuggested)
        <p role="status" class="text-xs font-medium text-blue-800">{{ __('initiation.suggest_help') }}</p>
    @endif

    @if ($example && blank($value))
        <p class="rounded bg-slate-50 px-3 py-2 text-xs italic leading-relaxed text-slate-600">{{ $example }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

@if ($canSuggest)
    {{-- A la pila, que se vacía fuera del formulario principal: HTML no permite
         formularios anidados, y el atributo `form` del botón existe para esto. --}}
    @push('outside-form')
        <form id="suggest-{{ $name }}" method="POST"
              action="{{ route($step->route().'.suggest', ['project' => $project, 'field' => $name]) }}"
              class="hidden">
            @csrf
        </form>
    @endpush
@endif
