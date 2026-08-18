@props(['task', 'showPercent' => true])

@php
    /** @var \App\Models\Task $task */
    $state = $task->state();
    $percent = (int) round((float) $task->percent_complete);

    // El distintivo lleva **texto además de color**: «terminada» no puede
    // depender de distinguir verde de ámbar, que es la deficiencia de visión más
    // común. Es la misma regla que ya seguía la ruta crítica en la lista.
    $badge = ['todo' => 'badge-neutral', 'doing' => 'badge-warn', 'done' => 'badge-ok'][$state];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    <span class="badge {{ $badge }}">{{ __("tasks.state_{$state}") }}</span>

    @if ($showPercent && $state === 'doing')
        <span class="text-[11px] tabular text-slate-500">{{ $percent }} %</span>
    @endif
</span>
