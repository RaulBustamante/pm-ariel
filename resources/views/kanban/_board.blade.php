@php
    $meta = [
        'todo' => ['label' => __('kanban.todo'), 'accent' => 'bg-slate-400'],
        'doing' => ['label' => __('kanban.doing'), 'accent' => 'bg-brand-600'],
        'done' => ['label' => __('kanban.done'), 'accent' => 'bar-ok'],
    ];

    // Límite de trabajo en curso. No se impide pasarse —el sistema no manda—
    // pero se dice, porque el número que importa en un tablero es cuántas cosas
    // hay empezadas al mismo tiempo.
    $wipLimit = 5;

    $canUpdate = auth()->user()?->can('update', $project) ?? false;

    // El costo es un permiso aparte del de ver el proyecto (regla 3 de
    // ProjectPolicy), así que se pregunta una vez aquí y no en cada tarjeta.
    $canSeeCosts = auth()->user()?->can('viewCosts', $project) ?? false;
@endphp

<div class="grid gap-3 md:grid-cols-3">
    @foreach ($meta as $key => $column)
        @php
            $tasks = $columns[$key];
            $overWip = $key === 'doing' && $tasks->count() > $wipLimit;
        @endphp

        <section class="{{ $compact ? '' : 'card' }} flex min-w-0 flex-col {{ $compact ? 'rounded-md bg-slate-50 ring-1 ring-slate-200' : '' }}">
            <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $column['accent'] }}" aria-hidden="true"></span>
                    <h3 class="truncate text-xs font-semibold uppercase tracking-wide text-slate-700">
                        {{ $column['label'] }}
                    </h3>
                </div>
                <span class="badge {{ $overWip ? 'badge-warn' : 'badge-neutral' }}">
                    {{ $tasks->count() }}@if ($key === 'doing')/{{ $wipLimit }}@endif
                </span>
            </div>

            @if ($overWip)
                <p class="border-b border-[var(--color-badge-warn-line)] bg-[var(--color-badge-warn-bg)] px-3 py-1.5 text-[11px] leading-snug text-[var(--color-badge-warn-fg)]">
                    {{ __('kanban.wip_exceeded', ['limit' => $wipLimit]) }}
                </p>
            @endif

            {{-- La columna es la zona donde se suelta. El atributo lo lee el
                 JavaScript; sin él, todo lo de abajo sigue funcionando igual. --}}
            <div class="min-h-[4rem] space-y-2 p-2" @if ($canUpdate) data-kanban-column="{{ $key }}" @endif>
                @forelse ($tasks as $task)
                    <article class="rounded-md border border-slate-200 bg-surface p-2.5 shadow-card transition-colors hover:border-brand-300"
                             @if ($canUpdate) data-kanban-card data-task-id="{{ $task->id }}" data-task-name="{{ $task->name }}" @endif
                             data-task-url="{{ route('projects.tasks.show', [$project, $task]) }}">
                        <div class="flex items-start justify-between gap-2">
                            {{-- El título es un enlace, no solo un doble clic: el
                                 doble clic no existe para quien navega con teclado
                                 ni para un lector de pantalla. El atajo se agrega
                                 encima; la puerta de verdad es esta. --}}
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                               class="min-w-0 flex-1 rounded text-[13px] font-medium leading-snug text-slate-900 hover:text-brand-700 hover:underline focus:outline-none focus:ring-2 focus:ring-hud-500">
                                {{ $task->name }}
                            </a>

                            @if ($task->is_critical)
                                <span class="badge badge-danger shrink-0">{{ __('tasks.critical') }}</span>
                            @endif
                        </div>

                        {{-- El avance, dentro de la tarjeta. Una columna «en curso»
                             sin porcentaje no distingue lo que arrancó ayer de lo
                             que lleva semanas al 90 %. --}}
                        @if ($task->state() === 'doing')
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="meter h-1.5 flex-1">
                                    <div class="meter-fill" style="width: {{ min(100, (int) $task->percent_complete) }}%"></div>
                                </div>
                                <span class="shrink-0 text-[11px] tabular text-slate-500">{{ (int) $task->percent_complete }} %</span>
                            </div>
                        @endif

                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                            @if ($task->wbs_code)
                                <span class="font-mono">{{ $task->wbs_code }}</span>
                            @endif

                            {{-- Si ya terminó, la fecha que importa es la real y no
                                 la planeada: preguntar «¿cuándo se entregó?» y que
                                 el tablero conteste con el plan es contestar otra
                                 pregunta. --}}
                            @if ($task->actual_finish)
                                <span class="text-[var(--color-badge-ok-fg)]">
                                    {{ __('tasks.actual_finish') }} {{ $task->actual_finish->format('d/m') }}
                                </span>
                            @elseif ($task->early_finish)
                                <span>{{ __('tasks.finish') }} {{ $task->early_finish->format('d/m') }}</span>
                            @endif

                            @if ($task->owner)
                                <span class="truncate">{{ $task->owner->name }}</span>
                            @endif

                            @if ($task->hasNotes())
                                <span title="{{ __('tasks.has_notes') }}">
                                    <span aria-hidden="true">✎</span>
                                    <span class="sr-only">{{ __('tasks.has_notes') }}</span>
                                </span>
                            @endif

                            {{-- Cuántas horas cuesta la tarjeta y cuánto dinero.
                                 Una columna «en curso» sin eso enseña seis
                                 tarjetas que parecen equivalentes cuando una se
                                 lleva la mitad del presupuesto. --}}
                            @if ($canSeeCosts && $costs->has($task->id))
                                @php $line = $costs->get($task->id); @endphp
                                <span class="tabular" title="{{ __('resources.cost') }}">
                                    {{ number_format($line['hours'], 0) }} h
                                    @if ($line['cost'] > 0)
                                        · {{ number_format($line['cost'], 0) }}
                                    @endif
                                </span>
                            @endif
                        </div>

                        @can('update', $project)
                            {{-- Botones y no solo arrastre: funcionan con teclado,
                                 con lector de pantalla y sin JavaScript. El
                                 arrastre se agrega **encima** de esto, nunca en su
                                 lugar. --}}
                            <div class="mt-2 flex gap-1 border-t border-slate-100 pt-2">
                                @foreach ($meta as $target => $targetColumn)
                                    @continue($target === $key)
                                    <form method="POST" action="{{ route('projects.kanban.move', [$project, $task]) }}">
                                        @csrf
                                        <input type="hidden" name="column" value="{{ $target }}">
                                        <button type="submit" class="btn btn-ghost btn-sm">
                                            {{ __("kanban.move_to_{$target}") }}
                                            <span class="sr-only"> — {{ $task->name }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endcan
                    </article>
                @empty
                    <p class="px-1 py-3 text-center text-[11px] text-slate-400">{{ __('kanban.column_empty') }}</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
