@extends('layouts.app')

@section('title', __('calendars.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'settings'])

    <p class="mb-4 max-w-3xl text-sm text-slate-600">{{ __('calendars.intro') }}</p>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($calendars as $calendar)
                @php $working = $calendar->toWorkingCalendar(); @endphp

                <section class="card">
                    <div class="card-header">
                        <div class="flex min-w-0 items-center gap-2">
                            <h2 class="card-title truncate">{{ $calendar->name }}</h2>
                            @if ($calendar->is_default)
                                <span class="badge badge-brand">{{ __('calendars.is_default') }}</span>
                            @endif
                        </div>

                        @can('update', $project)
                            @unless ($calendar->is_default)
                                <form method="POST" action="{{ route('projects.calendars.default', [$project, $calendar]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">{{ __('calendars.make_default') }}</button>
                                </form>
                            @endunless
                        @endcan
                    </div>

                    <div class="p-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($weekdays as $day)
                                @php $works = ($calendar->week[$day] ?? $calendar->week[(string) $day] ?? []) !== []; @endphp
                                <span class="badge {{ $works ? 'badge-ok' : 'badge-neutral' }}">
                                    {{ mb_substr(__("calendars.weekday_{$day}"), 0, 3) }}
                                </span>
                            @endforeach
                            <span class="ml-2 text-xs text-slate-500 self-center">{{ $calendar->timezone }}</span>
                        </div>

                        {{-- Días especiales --}}
                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ __('calendars.exceptions') }}
                            </h3>
                            <p class="mt-0.5 field-help">{{ __('calendars.exceptions_intro') }}</p>

                            @php $exceptions = $calendar->exceptions ?? []; @endphp

                            @if ($exceptions === [])
                                <p class="mt-2 text-xs text-slate-500">{{ __('calendars.no_exceptions') }}</p>
                            @else
                                <ul class="mt-2 space-y-1">
                                    @foreach ($exceptions as $date => $shifts)
                                        <li class="flex items-center justify-between gap-2 text-sm">
                                            <span class="text-slate-800">
                                                {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
                                                <span class="badge {{ $shifts === [] ? 'badge-danger' : 'badge-ok' }} ml-1">
                                                    {{ $shifts === [] ? __('calendars.holiday') : __('calendars.workday') }}
                                                </span>
                                            </span>

                                            @can('update', $project)
                                                <form method="POST" action="{{ route('projects.calendars.exception', [$project, $calendar]) }}">
                                                    @csrf
                                                    <input type="hidden" name="date" value="{{ $date }}">
                                                    <input type="hidden" name="action" value="remove">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        {{ __('calendars.remove_exception') }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @can('update', $project)
                                {{-- El formulario lleva a la vista previa, nunca
                                     directo a guardar: un feriado puede recorrer
                                     la entrega dos semanas y enterarse después de
                                     guardar es la peor forma de descubrirlo. --}}
                                <form method="POST" action="{{ route('projects.calendars.preview', [$project, $calendar]) }}"
                                      class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                                    @csrf
                                    <div>
                                        <label for="date-{{ $calendar->id }}" class="field-label">{{ __('calendars.date') }}</label>
                                        <input id="date-{{ $calendar->id }}" type="date" name="date" class="field" required>
                                    </div>
                                    <div>
                                        <label for="action-{{ $calendar->id }}" class="field-label">&nbsp;</label>
                                        <select id="action-{{ $calendar->id }}" name="action" class="field">
                                            <option value="holiday">{{ __('calendars.mark_holiday') }}</option>
                                            <option value="workday">{{ __('calendars.mark_workday') }}</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-secondary">{{ __('calendars.see_impact') }}</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        @can('update', $project)
            <aside>
                <form method="POST" action="{{ route('projects.calendars.store', $project) }}" class="card">
                    @csrf
                    <div class="card-header"><h2 class="card-title">{{ __('calendars.add') }}</h2></div>

                    <div class="space-y-3 p-4">
                        <div>
                            <label for="cal-name" class="field-label">{{ __('calendars.name') }}</label>
                            <input id="cal-name" type="text" name="name" class="field" required>
                        </div>

                        <div>
                            <label for="cal-key" class="field-label">{{ __('calendars.key') }}</label>
                            <input id="cal-key" type="text" name="key" class="field" pattern="[a-z0-9_-]+" required>
                            <p class="field-help mt-1">{{ __('calendars.key_help') }}</p>
                        </div>

                        <div>
                            <label for="cal-tz" class="field-label">{{ __('calendars.timezone') }}</label>
                            <input id="cal-tz" type="text" name="timezone" class="field"
                                   value="{{ config('app.timezone') }}" required>
                        </div>

                        <fieldset>
                            <legend class="field-label">{{ __('calendars.workdays') }}</legend>
                            <div class="grid grid-cols-2 gap-1">
                                @foreach ($weekdays as $day)
                                    <label class="flex items-center gap-1.5 text-xs">
                                        <input type="checkbox" name="days[]" value="{{ $day }}"
                                               @checked($day <= 5)
                                               class="rounded border-slate-300 text-brand-700">
                                        {{ __("calendars.weekday_{$day}") }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="cal-start" class="field-label">{{ __('calendars.from') }}</label>
                                <input id="cal-start" type="time" name="start" value="09:00" class="field" required>
                            </div>
                            <div>
                                <label for="cal-end" class="field-label">{{ __('calendars.to') }}</label>
                                <input id="cal-end" type="time" name="end" value="18:00" class="field" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full">{{ __('calendars.add') }}</button>
                    </div>
                </form>
            </aside>
        @endcan
    </div>
@endsection
