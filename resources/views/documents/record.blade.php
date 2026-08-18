@extends('layouts.app')

@section('title', __("documents.doc_{$code}"))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    @php
        $decisionBadge = [
            'accepted' => 'badge-ok',
            'accepted_with_reservations' => 'badge-warn',
            'rejected' => 'badge-danger',
        ];
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __("documents.doc_{$code}") }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __("records.help_{$code}") }}</p>
        </div>

        <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm shrink-0">
            {{ __('documents.title') }}
        </a>
    </div>

    <section class="card card-hud hud-in mb-4 p-4">
        <div class="flex flex-wrap items-end gap-8">
            @foreach ([
                ['total', $summary['total']],
                ['signed_count', $summary['signed']],
                ['draft_count', $summary['draft']],
                ['rejected_count', $summary['rejected']],
            ] as [$key, $value])
                <div>
                    <p class="stat-label">{{ __("records.{$key}") }}</p>
                    <p class="stat-value">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Un borrador se ve igual que una firmada en una lista, y hace creer
             que el proyecto está más cerrado de lo que está. --}}
        @if ($summary['draft'] > 0)
            <p class="badge-warn mt-3 block rounded-md border px-3 py-2 text-xs">{{ __('records.draft_warning') }}</p>
        @endif
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($records->isEmpty())
                <section class="card p-8 text-center text-sm text-slate-500">{{ __('records.empty') }}</section>
            @endif

            @foreach ($records as $row)
                <section class="card hud-in p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs tabular text-slate-500">{{ $row->reference() }}</p>
                            <h3 class="mt-0.5 text-sm font-semibold text-slate-900">{{ $row->subject }}</h3>

                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ $row->accepted_by_name }}@if ($row->accepted_by_role) · {{ $row->accepted_by_role }}@endif
                                @if ($row->accepted_by_org) · {{ $row->accepted_by_org }}@endif
                                · {{ $row->accepted_on?->format('d/m/Y') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <span class="badge {{ $decisionBadge[$row->decision] ?? 'badge-neutral' }}">
                                {{ __("records.decision_{$row->decision}") }}
                            </span>

                            @if ($row->isSigned())
                                <span class="badge badge-ok">{{ __('records.signed_on', ['date' => $row->signed_at?->format('d/m/Y')]) }}</span>
                            @else
                                <span class="badge badge-warn">{{ __('records.draft') }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($row->task)
                        <p class="mt-2 text-xs text-slate-600">
                            <span class="font-medium">{{ __('records.deliverable') }}:</span>
                            <a href="{{ route('projects.tasks.show', [$project, $row->task]) }}"
                               class="text-brand-700 underline hover:text-brand-800">{{ $row->task->name }}</a>
                        </p>
                    @endif

                    @if ($row->detail)
                        <p class="mt-2 whitespace-pre-line text-xs leading-relaxed text-slate-600">{{ $row->detail }}</p>
                    @endif

                    @if ($row->reservations)
                        <div class="mt-2 border-l-2 border-[var(--color-badge-warn-line)] pl-2">
                            <p class="text-[11px] font-medium text-slate-700">{{ __('records.reservations') }}</p>
                            <p class="whitespace-pre-line text-xs leading-relaxed text-slate-600">{{ $row->reservations }}</p>
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
                        <a href="{{ route('projects.documents.record.pdf', [$project, $code, $row]) }}"
                           class="btn btn-secondary btn-sm">{{ __('records.download') }}</a>

                        @can('update', $project)
                            @unless ($row->isSigned())
                                <a href="{{ route('projects.documents.record.edit', [$project, $code, $row]) }}"
                                   class="btn btn-ghost btn-sm">{{ __('common.edit') }}</a>

                                {{-- Firmar no se deshace: congela el acta y archiva
                                     su PDF. Por eso se confirma antes, y el texto
                                     dice qué pasa, no solo «¿seguro?». --}}
                                <form method="POST" action="{{ route('projects.documents.record.sign', [$project, $code, $row]) }}"
                                      onsubmit="return confirm('{{ __('records.sign_confirm') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('records.sign') }}</button>
                                </form>

                                <form method="POST" action="{{ route('projects.documents.record.destroy', [$project, $code, $row]) }}"
                                      onsubmit="return confirm('{{ __('common.confirm_title') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm">{{ __('common.delete') }}</button>
                                </form>
                            @endunless
                        @endcan
                    </div>

                    @if ($row->isSigned())
                        <p class="mt-2 text-[11px] text-slate-500">
                            {{ __('records.recorded_by', ['who' => $row->signedBy?->name ?? '—']) }}
                            · {{ __('records.checksum') }}
                            <span class="font-mono">{{ substr((string) $row->checksum, 0, 12) }}</span>
                        </p>
                    @endif
                </section>
            @endforeach
        </div>

        <aside class="space-y-4">
            @can('update', $project)
                <section class="card hud-in hud-in-3 p-4">
                    <h2 class="card-title mb-3">{{ $record ? __('records.edit') : __('records.open') }}</h2>

                    @if ($record)
                        <p class="mb-3 font-mono text-xs tabular text-slate-500">{{ $record->reference() }}</p>
                    @endif

                    <form method="POST" class="space-y-3"
                          action="{{ $record
                              ? route('projects.documents.record.update', [$project, $code, $record])
                              : route('projects.documents.record.store', [$project, $code]) }}">
                        @csrf
                        @if ($record) @method('PUT') @endif

                        <x-form-field name="subject" required
                                      :label="__('records.subject')"
                                      :value="$record?->subject"
                                      :help="__('records.subject_help')" />

                        <div class="space-y-1">
                            <label for="detail" class="block text-sm font-medium text-slate-700">{{ __('records.detail') }}</label>
                            <textarea id="detail" name="detail" rows="3" class="field">{{ old('detail', $record?->detail) }}</textarea>
                            <p class="text-xs text-slate-500">{{ __('records.detail_help') }}</p>
                        </div>

                        @if ($linksDeliverable)
                            <div class="space-y-1">
                                <label for="task_id" class="block text-sm font-medium text-slate-700">{{ __('records.deliverable') }}</label>
                                <select id="task_id" name="task_id" class="field">
                                    <option value="">{{ __('records.deliverable_none') }}</option>
                                    @foreach ($deliverables as $deliverable)
                                        <option value="{{ $deliverable->id }}"
                                                @selected((int) old('task_id', $record?->task_id) === $deliverable->id)>
                                            {{ $deliverable->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-slate-500">{{ __('records.deliverable_help') }}</p>
                            </div>
                        @endif

                        <div class="space-y-1">
                            <label for="decision" class="block text-sm font-medium text-slate-700">{{ __('records.decision') }}</label>
                            <select id="decision" name="decision" class="field">
                                @foreach ($decisions as $decision)
                                    <option value="{{ $decision }}" @selected(old('decision', $record?->decision) === $decision)>
                                        {{ __("records.decision_{$decision}") }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-500">{{ __('records.decision_help') }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="reservations" class="block text-sm font-medium text-slate-700">{{ __('records.reservations') }}</label>
                            <textarea id="reservations" name="reservations" rows="3" class="field">{{ old('reservations', $record?->reservations) }}</textarea>
                            <p class="text-xs text-slate-500">{{ __('records.reservations_help') }}</p>
                            @error('reservations')
                                <p role="alert" class="text-sm text-[var(--color-badge-danger-fg)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-slate-100 pt-3">
                            <p class="mb-2 text-sm font-semibold text-slate-900">{{ __('records.accepted_by') }}</p>

                            <div class="space-y-3">
                                <x-form-field name="accepted_by_name" required
                                              :label="__('records.accepted_by_name')"
                                              :value="$record?->accepted_by_name"
                                              :help="__('records.accepted_by_name_help')" />

                                <x-form-field name="accepted_by_role"
                                              :label="__('records.accepted_by_role')"
                                              :value="$record?->accepted_by_role" />

                                <x-form-field name="accepted_by_org"
                                              :label="__('records.accepted_by_org')"
                                              :value="$record?->accepted_by_org" />

                                <x-form-field name="accepted_on" type="date" required
                                              :label="__('records.accepted_on')"
                                              :value="$record?->accepted_on?->format('Y-m-d') ?? now()->format('Y-m-d')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>

                            @if ($record)
                                <a href="{{ route('projects.documents.record', [$project, $code]) }}" class="btn btn-secondary">
                                    {{ __('common.cancel') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </section>

                {{-- Qué es y qué no es la firma. Va junto al botón y no en una
                     ayuda escondida: un sello que promete más de lo que vale es
                     peor que no tener sello. --}}
                <section class="card hud-in hud-in-4 p-4">
                    <h2 class="card-title mb-2">{{ __('records.sign') }}</h2>
                    <p class="text-xs leading-relaxed text-slate-600">{{ __('records.sign_help') }}</p>
                    <p class="mt-2 border-t border-slate-100 pt-2 text-xs leading-relaxed text-slate-500">
                        {{ __('records.sign_disclaimer') }}
                    </p>
                </section>
            @endcan
        </aside>
    </div>
@endsection
