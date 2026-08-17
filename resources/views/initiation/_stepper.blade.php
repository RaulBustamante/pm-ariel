@php
    use App\Support\Initiation\InitiationStep;

    /** @var \App\Models\Project $project */
    /** @var \App\Models\ProjectCharter $charter */
    /** @var InitiationStep|null $step */
    $step = $step ?? null;
    $steps = InitiationStep::ordered();
@endphp

<nav aria-label="{{ __('initiation.title') }}" class="mb-6">
    <ol class="grid gap-2 sm:grid-cols-4">
        @foreach ($steps as $item)
            @php
                $isCurrent = $step !== null && $item === $step;
                $isDone = $charter->hasCompleted($item->value);
            @endphp

            <li>
                <a href="{{ route($item->route(), $project) }}"
                   @if ($isCurrent) aria-current="step" @endif
                   class="flex h-full flex-col gap-1 rounded-md border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-hud-500
                          {{ $isCurrent
                                ? 'border-hud-500 bg-brand-50 ring-1 ring-hud-500'
                                : ($isDone ? 'border-[var(--color-badge-ok-line)] bg-surface hover:border-[var(--color-badge-ok-line)]' : 'border-slate-200 bg-surface hover:border-slate-400') }}">
                    <span class="flex items-center gap-2 font-medium text-slate-900">
                        {{-- Número y palomita: el estado nunca se comunica solo con color. --}}
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold
                                     {{ $isDone ? 'bar-ok text-white' : ($isCurrent ? 'bg-brand-700 text-white' : 'bg-slate-200 text-slate-700') }}"
                              aria-hidden="true">
                            {{ $isDone ? '✓' : $item->position() }}
                        </span>
                        {{ $item->title() }}
                    </span>
                    <span class="text-xs leading-snug text-slate-600">{{ $item->purpose() }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</nav>
