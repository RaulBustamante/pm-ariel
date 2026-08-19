@extends('layouts.app')

@section('title', __('requirements.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __('requirements.title') }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('requirements.intro') }}</p>
        </div>

        <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm shrink-0">
            {{ __('documents.title') }}
        </a>
    </div>

    {{-- Los dos huecos, arriba de la lista.
         Una matriz de trazabilidad no existe para tener la tabla: existe para
         producir estos dos números. Enterrarlos al final del documento es
         garantizar que nadie los vea. --}}
    <div class="mb-4 grid gap-4 md:grid-cols-2">
        <section class="card hud-in p-4">
            <p class="stat-label">{{ __('requirements.orphans') }}</p>
            <p class="stat-value {{ $orphans->isNotEmpty() ? 'text-[var(--color-badge-danger-fg)]' : '' }}">
                {{ $orphans->count() }}
            </p>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-600">{{ __('requirements.orphans_help') }}</p>

            @if ($orphans->isNotEmpty())
                <ul class="mt-2 space-y-0.5 text-xs text-slate-700">
                    @foreach ($orphans->take(5) as $orphan)
                        <li class="truncate">
                            <span class="font-mono text-slate-500">{{ $orphan->reference() }}</span>
                            {{ $orphan->description }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card hud-in hud-in-2 p-4">
            <p class="stat-label">{{ __('requirements.unrequested') }}</p>
            <p class="stat-value {{ $unrequested->isNotEmpty() ? 'text-[var(--color-badge-warn-fg)]' : '' }}">
                {{ $unrequested->count() }}
            </p>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-600">{{ __('requirements.unrequested_help') }}</p>

            @if ($unrequested->isNotEmpty())
                <ul class="mt-2 space-y-0.5 text-xs text-slate-700">
                    @foreach ($unrequested->take(5) as $task)
                        <li class="truncate">
                            <span class="font-mono text-slate-500">{{ $task->wbs_code }}</span>
                            {{ $task->name }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <section class="card hud-in hud-in-3">
                <div class="card-header">
                    <h2 class="card-title">{{ __('requirements.matrix') }}</h2>
                    <span class="text-xs text-slate-500">{{ $requirements->count() }}</span>
                </div>

                @if ($requirements->isEmpty())
                    <p class="p-8 text-center text-sm text-slate-500">{{ __('requirements.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <caption class="sr-only">{{ __('requirements.matrix') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="w-20">{{ __('requirements.reference') }}</th>
                                    <th scope="col">{{ __('requirements.description') }}</th>
                                    <th scope="col" class="w-24">{{ __('requirements.priority') }}</th>
                                    <th scope="col" class="w-48">{{ __('requirements.delivered_by') }}</th>
                                    <th scope="col" class="w-28">{{ __('common.status') }}</th>
                                    @can('update', $project)
                                        <th scope="col" class="w-20"></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requirements as $requirement)
                                    <tr>
                                        <td class="font-mono text-xs tabular text-slate-600">{{ $requirement->reference() }}</td>

                                        <td>
                                            <span class="font-medium text-slate-900">{{ $requirement->description }}</span>
                                            @if ($requirement->origin)
                                                <span class="block text-[11px] text-slate-500">
                                                    {{ __('requirements.origin') }}: {{ $requirement->origin }}
                                                </span>
                                            @endif
                                            @if ($requirement->acceptance_criteria)
                                                <span class="mt-0.5 block whitespace-pre-line border-l-2 border-slate-200 pl-2 text-[11px] leading-relaxed text-slate-500">
                                                    {{ $requirement->acceptance_criteria }}
                                                </span>
                                            @endif
                                        </td>

                                        <td>
                                            <span class="badge {{ $requirement->priority === 'must' ? 'badge-brand' : 'badge-neutral' }}">
                                                {{ __("requirements.priority_{$requirement->priority}") }}
                                            </span>
                                        </td>

                                        <td class="text-xs">
                                            @if ($requirement->task)
                                                <a href="{{ route('projects.tasks.show', [$project, $requirement->task]) }}"
                                                   class="text-brand-700 underline hover:text-brand-800">{{ $requirement->task->name }}</a>
                                            @else
                                                {{-- El hueco se marca donde está, no solo en el
                                                     contador de arriba. --}}
                                                <span class="badge badge-danger">{{ __('requirements.nobody') }}</span>
                                            @endif
                                        </td>

                                        <td>
                                            <span class="badge {{ in_array($requirement->status, ['verified', 'delivered'], true) ? 'badge-ok' : 'badge-neutral' }}">
                                                {{ __("requirements.status_{$requirement->status}") }}
                                            </span>
                                        </td>

                                        @can('update', $project)
                                            <td>
                                                <form method="POST"
                                                      action="{{ route('projects.requirements.destroy', [$project, $requirement]) }}"
                                                      onsubmit="return confirm('{{ __('common.confirm_title') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded px-1 text-slate-400 hover:text-[var(--color-badge-danger-fg)]">
                                                        <span aria-hidden="true">✕</span>
                                                        <span class="sr-only">{{ __('common.delete') }}</span>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside>
            @can('update', $project)
                <section class="card hud-in hud-in-4 p-4">
                    <h2 class="card-title mb-3">{{ __('requirements.add') }}</h2>

                    <form method="POST" action="{{ route('projects.requirements.store', $project) }}" class="space-y-3">
                        @csrf

                        <div class="space-y-1">
                            <label for="description" class="block text-sm font-medium text-slate-700">
                                {{ __('requirements.description') }}
                            </label>
                            <textarea id="description" name="description" rows="2" class="field" required></textarea>
                            <p class="text-xs text-slate-500">{{ __('requirements.description_help') }}</p>
                        </div>

                        <x-form-field name="origin" :label="__('requirements.origin')" :help="__('requirements.origin_help')" />

                        <div class="space-y-1">
                            <label for="priority" class="block text-sm font-medium text-slate-700">{{ __('requirements.priority') }}</label>
                            <select id="priority" name="priority" class="field">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}" @selected($priority === 'should')>
                                        {{ __("requirements.priority_{$priority}") }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="task_id" class="block text-sm font-medium text-slate-700">{{ __('requirements.delivered_by') }}</label>
                            <select id="task_id" name="task_id" class="field">
                                <option value="">{{ __('requirements.nobody_yet') }}</option>
                                @foreach ($deliverables as $deliverable)
                                    <option value="{{ $deliverable->id }}">{{ $deliverable->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-500">{{ __('requirements.delivered_by_help') }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="acceptance_criteria" class="block text-sm font-medium text-slate-700">
                                {{ __('requirements.acceptance') }}
                            </label>
                            <textarea id="acceptance_criteria" name="acceptance_criteria" rows="2" class="field"></textarea>
                            <p class="text-xs text-slate-500">{{ __('requirements.acceptance_help') }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="status" class="block text-sm font-medium text-slate-700">{{ __('common.status') }}</label>
                            <select id="status" name="status" class="field">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($status === 'proposed')>
                                        {{ __("requirements.status_{$status}") }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                    </form>
                </section>
            @endcan
        </aside>
    </div>
@endsection
