@extends('layouts.app')

@section('title', __('initiation.title'))
@section('heading', $project->name)

@section('content')
    @php
        $lightClasses = match ($light) {
            'green' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
            'amber' => 'bg-amber-50 text-amber-900 ring-amber-200',
            default => 'bg-red-50 text-red-900 ring-red-200',
        };
        $lightMark = match ($light) { 'green' => '✓', 'amber' => '·', default => '!' };
    @endphp

    @include('initiation._stepper', ['step' => null])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-lg p-4 ring-1 {{ $lightClasses }}">
                <div class="flex items-start gap-3">
                    <span aria-hidden="true"
                          class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface/70 text-sm font-bold">
                        {{ $lightMark }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold">{{ __("initiation.health_{$light}") }}</p>
                        <p class="mt-0.5 text-xs">{{ __('initiation.health_complete_pct', ['percent' => $completion]) }}</p>
                    </div>
                </div>

                {{-- La barra repite el mismo dato que el texto de arriba: quien no
                     ve color lo lee, quien no lee lo ve. --}}
                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface/60"
                     role="progressbar" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="{{ __('initiation.health_complete_pct', ['percent' => $completion]) }}">
                    <div class="h-full bg-current opacity-60" style="width: {{ $completion }}%"></div>
                </div>
            </section>

            @include('initiation._findings')

            @if ($findings === [])
                <div class="rounded-lg border border-dashed border-emerald-300 bg-surface p-6 text-center text-sm text-slate-700">
                    {{ __('initiation.health_green') }}
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('initiation.project_code') }}</dt>
                        <dd class="font-mono text-slate-900">{{ $project->code }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('initiation.field_sponsor') }}</dt>
                        <dd class="text-slate-900">{{ $charter->sponsor?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('initiation.project_type') }}</dt>
                        <dd class="text-slate-900">{{ $charter->template?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('common.status') }}</dt>
                        <dd class="text-slate-900">
                            {{ $charter->isApproved()
                                ? __('initiation.approved_on', [
                                    'date' => $charter->approved_at->format('d/m/Y'),
                                    'name' => $charter->approver?->name ?? '—',
                                  ])
                                : __('initiation.not_approved') }}
                        </dd>
                    </div>
                </dl>
            </div>

            <a href="{{ route('projects.initiation.package', $project) }}"
               class="block rounded-md bg-blue-700 px-4 py-2 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('initiation.download_package') }}
            </a>

            @can('approve', $charter)
                @unless ($charter->isApproved())
                    <form method="POST" action="{{ route('projects.initiation.approve', $project) }}">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-emerald-600 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            {{ __('initiation.approve') }}
                        </button>
                    </form>
                @endunless
            @endcan
        </aside>
    </div>
@endsection
