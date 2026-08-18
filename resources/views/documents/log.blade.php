@extends('layouts.app')

@section('title', __("documents.doc_{$code}"))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __("documents.doc_{$code}") }}</h2>

            {{-- La ayuda no describe la pantalla: dice qué se anota aquí y qué
                 no. Es lo único que evita que el registro de decisiones se
                 llene de pendientes. --}}
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __("logs.help_{$code}") }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('projects.documents.log.pdf', [$project, $code] + array_filter($filters)) }}"
               class="btn btn-secondary btn-sm">{{ __('logs.download') }}</a>

            <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm">
                {{ __('documents.title') }}
            </a>
        </div>
    </div>

    {{-- Cuántos hay, cuántos siguen abiertos y cuántos ya se vencieron.
         Se cuentan sobre el registro completo y no sobre lo filtrado: un
         contador que cambia al filtrar deja de contestar «¿cómo va esto?». --}}
    <section class="card card-hud hud-in mb-4 p-4">
        <div class="flex flex-wrap items-end gap-8">
            <div>
                <p class="stat-label">{{ __('logs.total') }}</p>
                <p class="stat-value">{{ $summary['total'] }}</p>
            </div>

            <div>
                <p class="stat-label">{{ __('logs.open') }}</p>
                <p class="stat-value">{{ $summary['open'] }}</p>
            </div>

            @if (in_array('due', $fields, true))
                <div>
                    <p class="stat-label">{{ __('logs.overdue') }}</p>
                    <p class="stat-value {{ $summary['overdue'] > 0 ? 'text-[var(--color-badge-danger-fg)]' : '' }}">
                        {{ $summary['overdue'] }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">{{ __('logs.overdue_help') }}</p>
                </div>
            @endif
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="card hud-in hud-in-1">
                <div class="card-header">
                    <h2 class="card-title">{{ __("documents.doc_{$code}") }}</h2>
                    <span class="text-xs text-slate-500">
                        {{ __('logs.showing', ['shown' => $entries->count(), 'total' => $summary['total']]) }}
                    </span>
                </div>

                <form method="GET" action="{{ route('projects.documents.log', [$project, $code]) }}"
                      class="flex flex-wrap items-end gap-2 border-b border-slate-100 p-3">
                    <div class="min-w-[10rem] flex-1">
                        <label for="filter-q" class="sr-only">{{ __('logs.filter_search') }}</label>
                        <input type="search" id="filter-q" name="q" value="{{ $filters['q'] }}"
                               class="field" placeholder="{{ __('logs.filter_search') }}">
                    </div>

                    <div>
                        <label for="filter-status" class="sr-only">{{ __('common.status') }}</label>
                        <select id="filter-status" name="status" class="field">
                            <option value="">{{ __('logs.filter_all_statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                    {{ __("logs.status_{$status}") }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if (in_array('owner', $fields, true) && $candidates->isNotEmpty())
                        <div>
                            <label for="filter-owner" class="sr-only">{{ __('logs.owner') }}</label>
                            <select id="filter-owner" name="owner" class="field">
                                <option value="">{{ __('logs.filter_all_owners') }}</option>
                                @foreach ($candidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected($filters['owner'] === $candidate->id)>
                                        {{ $candidate->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('logs.filter') }}</button>

                    @if (array_filter($filters) !== [])
                        <a href="{{ route('projects.documents.log', [$project, $code]) }}" class="btn btn-ghost btn-sm">
                            {{ __('logs.filter_clear') }}
                        </a>
                    @endif
                </form>

                @if ($entries->isEmpty())
                    <p class="p-5 text-center text-sm text-slate-500">
                        {{ $summary['total'] === 0 ? __('logs.empty') : __('logs.empty_filtered') }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <caption class="sr-only">{{ __("documents.doc_{$code}") }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="w-24">{{ __('logs.reference') }}</th>
                                    <th scope="col">{{ __('logs.entry_title') }}</th>
                                    @if (in_array('owner', $fields, true))
                                        <th scope="col" class="w-36">{{ __('logs.owner') }}</th>
                                    @endif
                                    <th scope="col" class="w-40">{{ __('common.status') }}</th>
                                    <th scope="col" class="w-24"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $row)
                                    @php $isClosed = in_array($row->status, $closed, true); @endphp
                                    <tr>
                                        <td class="align-top">
                                            <span class="font-mono text-xs tabular text-slate-600">{{ $row->reference() }}</span>
                                            <span class="mt-0.5 block text-[11px] text-slate-500">
                                                {{ $row->occurred_on?->format('d/m/Y') }}
                                            </span>
                                        </td>

                                        <td class="align-top">
                                            <span class="font-medium text-slate-900">{{ $row->title }}</span>

                                            @if ($row->detail)
                                                {{-- Se conservan los saltos de línea: una lista escrita
                                                     renglón por renglón saldría como párrafo corrido. --}}
                                                <span class="mt-0.5 block whitespace-pre-line text-xs leading-relaxed text-slate-600">{{ $row->detail }}</span>
                                            @endif

                                            @if ($row->outcome)
                                                <span class="mt-1 block whitespace-pre-line border-l-2 border-slate-200 pl-2 text-xs leading-relaxed text-slate-500">
                                                    <span class="font-medium">{{ __('logs.outcome') }}:</span> {{ $row->outcome }}
                                                </span>
                                            @endif

                                            @if (in_array('priority', $fields, true) && $row->priority)
                                                <span class="badge badge-neutral mt-1 mr-1">{{ __("logs.priority_{$row->priority}") }}</span>
                                            @endif

                                            {{-- Vencido solo se marca si sigue abierto: una acción
                                                 entregada tarde ya no es un pendiente. --}}
                                            @if (in_array('due', $fields, true) && $row->due_on)
                                                <span class="badge {{ ! $isClosed && $row->due_on->isPast() ? 'badge-danger' : 'badge-neutral' }} mt-1">
                                                    {{ __('logs.due_on') }}: {{ $row->due_on->format('d/m/Y') }}
                                                </span>
                                            @endif
                                        </td>

                                        @if (in_array('owner', $fields, true))
                                            <td class="align-top text-xs text-slate-600">
                                                {{ $row->owner?->name ?? __('logs.owner_none') }}
                                            </td>
                                        @endif

                                        <td class="align-top">
                                            <span class="badge {{ $isClosed ? 'badge-ok' : 'badge-warn' }}">
                                                {{ __("logs.status_{$row->status}") }}
                                            </span>
                                        </td>

                                        <td class="align-top">
                                            @can('update', $project)
                                                <a href="{{ route('projects.documents.log.edit', [$project, $code, $row]) }}"
                                                   class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-4">
            @can('update', $project)
                {{-- El alta vive aquí, junto a la lista, y no detrás de otro
                     botón. Un registro se llena a media junta o no se llena, y
                     cada pantalla intermedia es una excusa para anotarlo en una
                     libreta y perderlo. --}}
                <section class="card hud-in hud-in-3 p-4">
                    <h2 class="card-title mb-3">{{ $entry ? __('logs.edit') : __('logs.add') }}</h2>

                    @if ($entry)
                        <p class="mb-3 font-mono text-xs tabular text-slate-500">{{ $entry->reference() }}</p>
                    @endif

                    <form method="POST" class="space-y-3"
                          action="{{ $entry
                              ? route('projects.documents.log.update', [$project, $code, $entry])
                              : route('projects.documents.log.store', [$project, $code]) }}">
                        @csrf
                        @if ($entry) @method('PUT') @endif

                        <x-form-field name="occurred_on" type="date" required
                                      :label="__('logs.occurred_on')"
                                      :value="$entry?->occurred_on?->format('Y-m-d') ?? now()->format('Y-m-d')"
                                      :help="__('logs.occurred_on_help')" />

                        <x-form-field name="title" required
                                      :label="__('logs.entry_title')"
                                      :value="$entry?->title"
                                      :help="__('logs.entry_title_help')" />

                        <div class="space-y-1">
                            <label for="detail" class="block text-sm font-medium text-slate-700">{{ __('logs.detail') }}</label>
                            <textarea id="detail" name="detail" rows="4" class="field">{{ old('detail', $entry?->detail) }}</textarea>
                        </div>

                        <div class="space-y-1">
                            <label for="status" class="block text-sm font-medium text-slate-700">{{ __('common.status') }}</label>
                            <select id="status" name="status" class="field">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $entry?->status) === $status)>
                                        {{ __("logs.status_{$status}") }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if (in_array('owner', $fields, true))
                            <div class="space-y-1">
                                <label for="owner_id" class="block text-sm font-medium text-slate-700">{{ __('logs.owner') }}</label>
                                <select id="owner_id" name="owner_id" class="field">
                                    <option value="">{{ __('logs.owner_none') }}</option>
                                    @foreach ($candidates as $candidate)
                                        <option value="{{ $candidate->id }}"
                                                @selected((int) old('owner_id', $entry?->owner_id) === $candidate->id)>
                                            {{ $candidate->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if (in_array('due', $fields, true))
                            <x-form-field name="due_on" type="date"
                                          :label="__('logs.due_on')"
                                          :value="$entry?->due_on?->format('Y-m-d')" />
                        @endif

                        @if (in_array('priority', $fields, true))
                            <div class="space-y-1">
                                <label for="priority" class="block text-sm font-medium text-slate-700">{{ __('logs.priority') }}</label>
                                <select id="priority" name="priority" class="field">
                                    <option value="">{{ __('logs.priority_none') }}</option>
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', $entry?->priority) === $priority)>
                                            {{ __("logs.priority_{$priority}") }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if (in_array('outcome', $fields, true))
                            <div class="space-y-1">
                                <label for="outcome" class="block text-sm font-medium text-slate-700">{{ __('logs.outcome') }}</label>
                                <textarea id="outcome" name="outcome" rows="3" class="field">{{ old('outcome', $entry?->outcome) }}</textarea>
                                <p class="text-xs text-slate-500">{{ __('logs.outcome_help') }}</p>
                            </div>
                        @endif

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>

                            @if ($entry)
                                <a href="{{ route('projects.documents.log', [$project, $code]) }}" class="btn btn-secondary">
                                    {{ __('common.cancel') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($entry)
                        {{-- Fuera del formulario de arriba: un `<form>` dentro de
                             otro no es HTML válido. --}}
                        <form method="POST" class="mt-3 border-t border-slate-100 pt-3"
                              action="{{ route('projects.documents.log.destroy', [$project, $code, $entry]) }}"
                              onsubmit="return confirm('{{ __('logs.confirm_delete') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm">{{ __('common.delete') }}</button>
                        </form>
                    @endif
                </section>
            @endcan
        </aside>
    </div>
@endsection
