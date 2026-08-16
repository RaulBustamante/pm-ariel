@php
    use App\Models\Stakeholder;

    /** @var \Illuminate\Support\Collection<int, Stakeholder> $stakeholders */
    $stakeholders = $project->stakeholders;

    // Se agrupan por celda para que dos interesados con el mismo par no se
    // dibujen encimados y uno tape al otro.
    $cells = $stakeholders->groupBy(fn (Stakeholder $s): string => $s->power.'-'.$s->interest);

    $quadrantOf = function (int $power, int $interest): string {
        $high = Stakeholder::HIGH_THRESHOLD;

        return match (true) {
            $power >= $high && $interest >= $high => 'bg-red-50',
            $power >= $high => 'bg-amber-50',
            $interest >= $high => 'bg-sky-50',
            default => 'bg-slate-50',
        };
    };
@endphp

<section aria-labelledby="matrix-heading" class="rounded-lg bg-white p-4 ring-1 ring-slate-200">
    <h2 id="matrix-heading" class="text-sm font-semibold text-slate-900">{{ __('initiation.matrix_title') }}</h2>
    <p class="mt-1 text-xs text-slate-600">{{ __('initiation.matrix_help') }}</p>

    <div class="mt-4 overflow-x-auto">
        <div class="flex gap-2">
            <div class="flex w-6 items-center justify-center">
                <span class="whitespace-nowrap text-[11px] font-medium text-slate-600 [writing-mode:vertical-rl] [transform:rotate(180deg)]">
                    {{ __('initiation.matrix_axis_power') }}
                </span>
            </div>

            <div class="min-w-[20rem] flex-1">
                {{-- Poder de 5 abajo hacia arriba, interés de 1 a 5 de izquierda a
                     derecha: la esquina superior derecha es la que duele. --}}
                <div class="grid grid-cols-5 gap-1">
                    @foreach ([5, 4, 3, 2, 1] as $power)
                        @foreach ([1, 2, 3, 4, 5] as $interest)
                            @php $inCell = $cells->get("{$power}-{$interest}", collect()); @endphp
                            <div class="min-h-[3.25rem] rounded border border-slate-200 p-1 {{ $quadrantOf($power, $interest) }}">
                                @foreach ($inCell as $person)
                                    <span class="mb-0.5 block truncate rounded bg-white/80 px-1 text-[11px] leading-tight text-slate-800 ring-1 ring-slate-300"
                                          title="{{ $person->name }}">
                                        {{ $person->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>

                <p class="mt-1 text-center text-[11px] font-medium text-slate-600">
                    {{ __('initiation.matrix_axis_interest') }}
                </p>
            </div>
        </div>
    </div>

    {{-- La leyenda no es decorativa: sin ella el color de cada celda no significa
         nada, y la matriz se vuelve un adorno. --}}
    <dl class="mt-4 grid gap-2 text-xs sm:grid-cols-2">
        @foreach ([
            'manage_closely' => 'bg-red-50 ring-red-200',
            'keep_satisfied' => 'bg-amber-50 ring-amber-200',
            'keep_informed' => 'bg-sky-50 ring-sky-200',
            'monitor' => 'bg-slate-50 ring-slate-200',
        ] as $quadrant => $classes)
            <div class="rounded px-2 py-1.5 ring-1 {{ $classes }}">
                <dt class="font-semibold text-slate-900">{{ __("initiation.quadrant_{$quadrant}") }}</dt>
                <dd class="text-slate-700">{{ __("initiation.strategy_{$quadrant}") }}</dd>
            </div>
        @endforeach
    </dl>
</section>
