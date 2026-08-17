@extends('layouts.app')

@section('title', __('calendars.impact_title'))
@section('heading', $project->name)

@section('content')
    @php
        $durations = new \App\Support\Scheduling\DurationParser;
        $working = $calendar->toWorkingCalendar();

        $slip = ($before !== null && $after !== null)
            ? $working->workingMinutesBetween($before, $after)
            : 0;
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('calendars.impact_title') }}</h2>
            </div>

            <div class="space-y-4 p-5">
                <p class="text-sm text-slate-700">
                    {{ $date->format('d/m/Y') }} —
                    <span class="font-semibold">
                        {{ $action === 'holiday' ? __('calendars.mark_holiday') : __('calendars.mark_workday') }}
                    </span>
                </p>

                @if ($movedTasks === 0)
                    <p class="rounded-md bg-[var(--color-badge-ok-bg)] px-4 py-3 text-sm text-[var(--color-badge-ok-fg)] ring-1 ring-[var(--color-badge-ok-line)]">
                        {{ __('calendars.impact_no_change') }}
                    </p>
                @else
                    {{-- El número que importa va primero y con peso: cuánto se
                         recorre la entrega. Lo demás es contexto. --}}
                    <div class="rounded-md px-4 py-3 text-sm ring-1
                                {{ $slip > 0 ? 'badge-warn' : 'badge-ok' }}">
                        <p class="text-base font-semibold">
                            {{ $slip > 0
                                ? __('calendars.impact_warning', ['amount' => $durations->toHuman($slip)])
                                : __('calendars.impact_earlier', ['amount' => $durations->toHuman(abs($slip))]) }}
                        </p>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-slate-600">{{ __('calendars.impact_before') }}</dt>
                            <dd class="mt-0.5 font-semibold">{{ $before?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-600">{{ __('calendars.impact_after') }}</dt>
                            <dd class="mt-0.5 font-semibold">{{ $after?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-600">{{ __('calendars.impact_moved') }}</dt>
                            <dd class="mt-0.5 font-semibold">{{ $movedTasks }}</dd>
                        </div>
                    </dl>
                @endif

                <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    <form method="POST" action="{{ route('projects.calendars.exception', [$project, $calendar]) }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                        <input type="hidden" name="action" value="{{ $action }}">
                        <button type="submit" class="btn btn-primary">{{ __('calendars.apply') }}</button>
                    </form>

                    <a href="{{ route('projects.calendars.index', $project) }}" class="btn btn-ghost">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
