@php
    use App\Models\Risk;

    /** @var \Illuminate\Support\Collection<int, Risk> $risks */
    $risks = $project->risks;
    $cells = $risks->groupBy(fn (Risk $r): string => $r->probability.'-'.$r->impact);

    // Mismos cortes que Risk::level(). Si aquí dijeran otra cosa, la matriz y la
    // etiqueta de cada riesgo se contradirían en la misma pantalla.
    $shade = fn (int $probability, int $impact): string => match (true) {
        $probability * $impact >= 15 => 'bg-[var(--color-badge-danger-bg)] border-[var(--color-badge-danger-line)]',
        $probability * $impact >= 9 => 'bg-[var(--color-badge-warn-bg)] border-[var(--color-badge-warn-line)]',
        $probability * $impact >= 4 => 'bg-[var(--color-badge-warn-bg)] border-[var(--color-badge-warn-line)]',
        default => 'bg-[var(--color-badge-ok-bg)] border-[var(--color-badge-ok-line)]',
    };
@endphp

<section aria-labelledby="risk-matrix-heading" class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
    <h2 id="risk-matrix-heading" class="text-sm font-semibold text-slate-900">{{ __('initiation.matrix_risk_title') }}</h2>
    <p class="mt-1 text-xs text-slate-600">{{ __('initiation.matrix_risk_help') }}</p>

    <div class="mt-4 flex gap-2">
        <div class="flex w-6 items-center justify-center">
            <span class="whitespace-nowrap text-[11px] font-medium text-slate-600 [writing-mode:vertical-rl] [transform:rotate(180deg)]">
                {{ __('initiation.risk_probability') }} →
            </span>
        </div>

        <div class="min-w-0 flex-1">
            <div class="grid grid-cols-5 gap-1">
                @foreach ([5, 4, 3, 2, 1] as $probability)
                    @foreach ([1, 2, 3, 4, 5] as $impact)
                        @php $inCell = $cells->get("{$probability}-{$impact}", collect()); @endphp
                        <div class="flex min-h-[2.75rem] flex-wrap content-start gap-0.5 rounded border p-1 {{ $shade($probability, $impact) }}">
                            @foreach ($inCell as $risk)
                                <span class="rounded bg-surface/80 px-1 text-[11px] font-medium leading-tight text-slate-800 ring-1 ring-slate-300"
                                      title="{{ $risk->description }}">
                                    {{ $risk->code }}
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>

            <p class="mt-1 text-center text-[11px] font-medium text-slate-600">
                {{ __('initiation.risk_impact') }} →
            </p>
        </div>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-1.5 text-[11px]">
        @foreach ([
            'critical' => 'bg-[var(--color-badge-danger-bg)] ring-[var(--color-badge-danger-line)]',
            'high' => 'bg-[var(--color-badge-warn-bg)] ring-[var(--color-badge-warn-line)]',
            'medium' => 'bg-[var(--color-badge-warn-bg)] ring-[var(--color-badge-warn-line)]',
            'low' => 'bg-[var(--color-badge-ok-bg)] ring-[var(--color-badge-ok-line)]',
        ] as $level => $classes)
            <div class="rounded px-2 py-1 ring-1 {{ $classes }}">
                <dt class="inline font-semibold text-slate-900">{{ __("initiation.level_{$level}") }}</dt>
            </div>
        @endforeach
    </dl>
</section>
