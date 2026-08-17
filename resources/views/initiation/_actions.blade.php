@php
    /** @var \App\Support\Initiation\InitiationStep $step */
    $previous = $step->previous();
    $next = $step->next();
@endphp

<div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
    <button type="submit"
            class="btn btn-primary">
        {{ $next !== null ? __('initiation.save_and_continue') : __('common.save') }}
    </button>

    {{-- Guardar y salir en el mismo formulario: quien se queda a medias no debe
         tener que elegir entre perder lo escrito o terminar el recorrido. --}}
    <button type="submit" name="action" value="exit"
            class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-hud-500">
        {{ __('initiation.save_and_exit') }}
    </button>

    @if ($previous !== null)
        <a href="{{ route($previous->route(), $project) }}"
           class="ml-auto rounded-md px-3 py-2 text-sm text-slate-600 underline hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-hud-500">
            {{ __('initiation.back') }}
        </a>
    @endif
</div>

<p class="text-xs text-slate-500">{{ __('initiation.step_of', ['current' => $step->position(), 'total' => count(\App\Support\Initiation\InitiationStep::ordered())]) }}</p>
