@php
    use App\Support\Initiation\Finding;

    /** @var list<Finding> $findings */
    $blocking = array_filter($findings, fn (Finding $f): bool => $f->isBlocking());
    $warnings = array_filter($findings, fn (Finding $f): bool => ! $f->isBlocking());
@endphp

@if ($findings !== [])
    <section aria-labelledby="findings-heading"
             class="mb-6 rounded-lg border border-slate-200 bg-surface p-4">
        <h2 id="findings-heading" class="text-sm font-semibold text-slate-900">
            {{ __('initiation.health') }}
        </h2>

        <ul class="mt-3 space-y-3">
            @foreach ([...$blocking, ...$warnings] as $finding)
                <li class="flex gap-3">
                    {{-- Símbolo además del color: quien no distingue rojo de ámbar
                         necesita saber igual qué es obligatorio y qué es consejo. --}}
                    <span aria-hidden="true"
                          class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold
                                 {{ $finding->isBlocking() ? 'bg-[var(--color-badge-danger-bg)] text-[var(--color-badge-danger-fg)]' : 'bg-[var(--color-badge-warn-bg)] text-[var(--color-badge-warn-fg)]' }}">
                        {{ $finding->isBlocking() ? '!' : '·' }}
                    </span>

                    <div class="text-sm">
                        <p class="font-medium text-slate-900">
                            {{ $finding->message }}
                            <span class="sr-only">
                                — {{ $finding->isBlocking() ? __('initiation.health_red') : __('initiation.health_amber') }}
                            </span>
                        </p>
                        {{-- El porqué siempre a la vista. Un semáforo que solo dice
                             "incompleto" obliga al usuario a adivinar. --}}
                        <p class="mt-0.5 text-slate-600">{{ $finding->why }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
