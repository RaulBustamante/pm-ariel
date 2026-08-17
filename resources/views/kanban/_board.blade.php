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

            <div class="min-h-[4rem] space-y-2 p-2">
                @forelse ($tasks as $task)
                    <article class="rounded-md border border-slate-200 bg-surface p-2.5 shadow-card transition-colors hover:border-brand-300">
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 flex-1 text-[13px] font-medium leading-snug text-slate-900">
                                {{ $task->name }}
                            </p>

                            @if ($task->is_critical)
                                <span class="badge badge-danger shrink-0">{{ __('tasks.critical') }}</span>
                            @endif
                        </div>

                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                            @if ($task->wbs_code)
                                <span class="font-mono">{{ $task->wbs_code }}</span>
                            @endif

                            @if ($task->early_finish)
                                <span>{{ __('tasks.finish') }} {{ $task->early_finish->format('d/m') }}</span>
                            @endif

                            @if ($task->owner)
                                <span class="truncate">{{ $task->owner->name }}</span>
                            @endif
                        </div>

                        @can('update', $project)
                            {{-- Botones y no arrastre: funcionan con teclado, con
                                 lector de pantalla y sin JavaScript. El arrastre
                                 se agrega encima cuando toque, no en lugar de
                                 esto. --}}
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
