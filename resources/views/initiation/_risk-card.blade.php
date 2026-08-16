@php
    use App\Models\Risk;
    /** @var Risk $risk */
    $levelClasses = match ($risk->level()) {
        Risk::LEVEL_CRITICAL => 'bg-red-100 text-red-900',
        Risk::LEVEL_HIGH => 'bg-orange-100 text-orange-900',
        Risk::LEVEL_MEDIUM => 'bg-amber-100 text-amber-900',
        default => 'bg-emerald-100 text-emerald-900',
    };
@endphp

<li class="rounded-lg bg-white p-4 ring-1 ring-slate-200">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">
                <span class="font-mono text-xs text-slate-500">{{ $risk->code }}</span>
                {{ $risk->description }}
            </p>
            @if (filled($risk->cause) || filled($risk->effect))
                <p class="mt-1 text-xs text-slate-600">
                    @if (filled($risk->cause)) <span class="font-medium">{{ __('initiation.risk_cause') }}:</span> {{ $risk->cause }} @endif
                    @if (filled($risk->effect)) <span class="ml-2 font-medium">{{ __('initiation.risk_effect') }}:</span> {{ $risk->effect }} @endif
                </p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2 text-xs">
            @if ($risk->kind === Risk::KIND_OPPORTUNITY)
                <span class="rounded-full bg-sky-100 px-2 py-0.5 font-medium text-sky-900">
                    {{ __('initiation.risk_kind_opportunity') }}
                </span>
            @endif

            {{-- Nivel con texto y número, no solo color de fondo. --}}
            <span class="rounded-full px-2 py-0.5 font-medium {{ $levelClasses }}">
                {{ __("initiation.level_{$risk->level()}") }} ({{ $risk->score() }})
            </span>

            <form method="POST" action="{{ route('projects.risks.destroy', [$project, $risk]) }}"
                  onsubmit="return confirm('{{ __('common.confirm_title') }}')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded text-red-700 underline hover:text-red-900 focus:outline-none focus:ring-2 focus:ring-red-600">
                    {{ __('common.delete') }}<span class="sr-only"> — {{ $risk->code }}</span>
                </button>
            </form>
        </div>
    </div>

    <div class="mt-3 border-t border-slate-100 pt-3">
        @forelse ($risk->responses as $response)
            <div class="mb-2 flex items-start justify-between gap-3 text-xs">
                <p class="text-slate-700">
                    <span class="font-medium">{{ __("initiation.strategy_{$response->strategy}") }}</span>
                    — {{ $response->description }}
                    @if ($response->owner_id)
                        <span class="text-slate-500">· {{ $response->owner?->name }}</span>
                    @endif
                    @if ($response->due_date)
                        <span class="text-slate-500">· {{ $response->due_date->format('d/m/Y') }}</span>
                    @endif
                </p>

                <form method="POST" action="{{ route('projects.risks.responses.destroy', [$project, $risk, $response]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="shrink-0 rounded text-slate-500 underline hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                        {{ __('common.delete') }}
                    </button>
                </form>
            </div>
        @empty
            <p class="mb-2 text-xs {{ $risk->needsResponse() ? 'font-medium text-red-800' : 'text-slate-500' }}">
                {{ __('initiation.no_responses') }}
                <x-help-term term="risk_response" />
            </p>
        @endforelse

        <details class="text-xs">
            <summary class="cursor-pointer rounded font-medium text-blue-700 underline focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('initiation.add_response') }}
            </summary>

            <form method="POST" action="{{ route('projects.risks.responses.store', [$project, $risk]) }}"
                  class="mt-3 space-y-2 rounded-md bg-slate-50 p-3">
                @csrf

                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block font-medium text-slate-700">{{ __('initiation.response_strategy') }}</span>
                        <select name="strategy" required
                                class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                            @foreach (\App\Models\RiskResponse::STRATEGIES as $strategy)
                                <option value="{{ $strategy }}">{{ __("initiation.strategy_{$strategy}") }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block font-medium text-slate-700">{{ __('initiation.response_owner') }}</span>
                        <select name="owner_id"
                                class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                            <option value="">—</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1 block font-medium text-slate-700">{{ __('initiation.response_description') }}</span>
                    <textarea name="description" rows="2" required
                              class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600"></textarea>
                </label>

                <label class="block">
                    <span class="mb-1 block font-medium text-slate-700">{{ __('initiation.response_due') }}</span>
                    <input type="date" name="due_date"
                           class="block rounded-md border-slate-300 text-xs shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                </label>

                <button type="submit"
                        class="rounded-md bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1">
                    {{ __('common.save') }}
                </button>
            </form>
        </details>
    </div>
</li>
