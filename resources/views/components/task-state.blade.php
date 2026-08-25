@props(['task', 'showPercent' => true])

@php
    /** @var \App\Models\Task $task */
    $state = $task->state();
    $percent = (int) round((float) $task->percent_complete);

    // El distintivo lleva **texto además de color**: «terminada» no puede
    // depender de distinguir verde de ámbar, que es la deficiencia de visión más
    // común. Es la misma regla que ya seguía la ruta crítica en la lista.
    $badge = ['todo' => 'badge-neutral', 'doing' => 'badge-warn', 'done' => 'badge-ok'][$state];

    // La espera va en su **propio** distintivo, al lado y no en lugar del otro.
    // Son dos ejes: el avance dice cuánto se hizo y la espera por qué no avanza,
    // y una tarea al 85 % que espera aprobación es las dos cosas. Reemplazar uno
    // con el otro habría borrado el 85 %.
    $waiting = $task->waitingReason();
    $waitingDays = $task->waitingDays();
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-1.5']) }}>
    <span class="badge {{ $badge }}">{{ __("tasks.state_{$state}") }}</span>

    @if ($showPercent && $state === 'doing')
        <span class="text-[11px] tabular text-slate-500">{{ $percent }} %</span>
    @endif

    @if ($waiting !== null)
        {{-- El título lleva la frase completa con la fecha. El distintivo tiene
             que caber en una celda de la Lista, y «· 13 d» cabe donde «Esperando
             desde el 12/08/2026 (13 días)» no. --}}
        <span class="badge badge-warn"
              @if ($task->waiting_since)
                  title="{{ __('tasks.waiting_since_date', [
                      'date' => $task->waiting_since->format('d/m/Y'),
                      'count' => $waitingDays,
                  ]) }}"
              @endif>
            {{ __("tasks.waiting_{$waiting->value}") }}
            @if ($waitingDays !== null)
                <span class="tabular font-normal opacity-80">· {{ __('tasks.waiting_days_short', ['count' => $waitingDays]) }}</span>
            @endif
        </span>
    @endif
</span>
