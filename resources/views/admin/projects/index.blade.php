@extends('layouts.app')

@section('title', __('project_overview.title'))
@section('heading', __('project_overview.title'))

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-600">{{ __('project_overview.intro') }}</p>

        <p class="whitespace-nowrap rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
            {{ __('project_overview.total', ['count' => $totalProjects]) }}
        </p>
    </div>

    {{-- «¿Qué trae Alfredo?» en un paso. Es un GET normal: la respuesta queda en
         la dirección y se puede compartir o guardar. --}}
    <form method="GET" action="{{ route('admin.projects.index') }}"
          class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-surface p-4 ring-1 ring-slate-200">
        <div>
            <label for="user-filter" class="block text-xs font-medium text-slate-700">
                {{ __('project_overview.filter_by_user') }}
            </label>
            <select id="user-filter" name="user"
                    class="mt-1 block max-w-xs rounded-md border-slate-300 text-sm shadow-sm focus:border-hud-500 focus:ring-2 focus:ring-hud-500">
                <option value="">{{ __('project_overview.all_users') }}</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->id }}" @selected($filtered?->id === $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-secondary btn-sm">{{ __('project_overview.apply_filter') }}</button>

        @if ($filtered)
            <a href="{{ route('admin.projects.index') }}"
               class="text-xs font-medium text-brand-700 underline hover:no-underline">
                {{ __('project_overview.clear_filter') }}
            </a>
        @endif
    </form>

    @if ($filtered && $projects->total() === 0)
        {{-- La respuesta más útil de esta pantalla suele ser «ninguno». Decirlo
             con una tabla vacía haría dudar de si el filtro funcionó. --}}
        <div class="mb-6 rounded-lg border border-dashed border-slate-300 bg-surface p-6 text-center text-sm text-slate-600">
            {{ __('project_overview.none_for_user', ['name' => $filtered->name]) }}
        </div>
    @elseif ($projects->isEmpty())
        <div class="mb-6 rounded-lg border border-dashed border-slate-300 bg-surface p-6 text-center text-sm text-slate-600">
            {{ __('project_overview.empty') }}
        </div>
    @else
        <div class="mb-4 overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('project_overview.title') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-3 py-2">{{ __('project_overview.project') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('project_overview.owner') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('project_overview.team') }}</th>
                        <th scope="col" class="px-2 py-2 text-right"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-3 py-2 align-top">
                                <a href="{{ route('projects.dashboard', $project) }}"
                                   class="font-medium text-brand-700 hover:underline">{{ $project->name }}</a>
                                <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $project->code }}</p>
                            </td>

                            <td class="px-3 py-2 align-top">
                                <span class="{{ $filtered && $project->owner_id === $filtered->id ? 'font-semibold text-brand-700' : 'text-slate-700' }}">
                                    {{ $project->owner?->name ?? '—' }}
                                </span>
                            </td>

                            <td class="px-3 py-2 align-top">
                                @if ($project->members->isEmpty())
                                    {{-- Un proyecto sin equipo es un dato, no un
                                         hueco: alguien lo creó y nadie lo puede
                                         trabajar. --}}
                                    <span class="text-xs italic text-[var(--color-badge-danger-fg)]">
                                        {{ __('project_overview.no_team') }}
                                    </span>
                                @else
                                    <ul class="space-y-0.5">
                                        @foreach ($project->members as $member)
                                            <li class="text-xs {{ $filtered && $member->id === $filtered->id ? 'font-semibold text-brand-700' : 'text-slate-600' }}">
                                                {{ $member->name }}
                                                {{-- El rol se dice con las
                                                     etiquetas que ya existen en
                                                     `projects`, no con copias
                                                     nuevas: dos traducciones del
                                                     mismo rol acaban diciendo
                                                     cosas distintas. --}}
                                                <span class="text-slate-400">·
                                                    {{ __('projects.role_'.$member->pivot->project_role) }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-2 py-2 text-right align-top">
                                <a href="{{ route('projects.dashboard', $project) }}" class="btn btn-secondary btn-sm">
                                    {{ __('project_overview.open') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $projects->links() }}
    @endif

    {{-- Quién no tiene nada asignado. Es el hallazgo que esta pantalla da gratis
         y que no se ve en ninguna otra: una cuenta activa con cero proyectos
         casi siempre es un alta que quedó a medias. --}}
    @if ($idle->isNotEmpty())
        <div class="mt-6 rounded-lg bg-surface p-4 ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('project_overview.idle_title') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('project_overview.idle_hint') }}</p>

            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($idle as $user)
                    <li>
                        <span class="inline-block rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200">
                            {{ $user->name }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
