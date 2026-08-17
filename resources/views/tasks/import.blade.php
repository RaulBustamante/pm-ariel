@extends('layouts.app')

@section('title', __('import.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'list'])

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($errors_list !== [])
                <div role="alert" class="card border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">{{ __('import.problems_found') }}</p>
                    <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-amber-900">
                        @foreach ($errors_list as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-amber-800">{{ __('import.problems_help') }}</p>
                </div>
            @endif

            @if ($preview === null)
                <form method="POST" action="{{ route('projects.tasks.import.preview', $project) }}"
                      enctype="multipart/form-data" class="card">
                    @csrf

                    <div class="card-header"><h2 class="card-title">{{ __('import.title') }}</h2></div>

                    <div class="space-y-3 p-4">
                        <p class="text-sm text-slate-600">{{ __('import.intro') }}</p>

                        <div>
                            <label for="file-field" class="field-label">{{ __('import.file') }}</label>
                            <input id="file-field" type="file" name="file" accept=".csv,text/csv" class="field" required>
                            @error('file') <p role="alert" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="replace" value="1" class="mt-0.5 rounded border-slate-300 text-brand-700">
                            <span>
                                <span class="font-medium text-slate-800">{{ __('import.replace') }}</span>
                                <span class="block field-help">{{ __('import.replace_help') }}</span>
                            </span>
                        </label>

                        <button type="submit" class="btn btn-primary">{{ __('import.see_preview') }}</button>
                    </div>
                </form>
            @else
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <h2 class="card-title">{{ __('import.preview_title', ['count' => count($preview)]) }}</h2>
                    </div>

                    <div class="max-h-[28rem] overflow-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('tasks.row') }}</th>
                                    <th scope="col">{{ __('tasks.name') }}</th>
                                    <th scope="col">{{ __('tasks.duration') }}</th>
                                    <th scope="col">{{ __('tasks.predecessors') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview as $row)
                                    <tr>
                                        <td class="font-mono text-xs text-slate-400">{{ $row['row'] }}</td>
                                        <td>
                                            <span style="padding-left: {{ $row['level'] * 1.25 }}rem" class="inline-block">
                                                {{ $row['name'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['duration'] ?: '0' }}</td>
                                        <td class="font-mono text-xs">{{ $row['predecessors'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($preview !== [])
                        <form method="POST" action="{{ route('projects.tasks.import.store', $project) }}"
                              class="flex flex-wrap items-center gap-3 border-t border-slate-200 p-4">
                            @csrf
                            <input type="hidden" name="payload" value="{{ $payload }}">
                            <input type="hidden" name="replace" value="{{ $replace ? 1 : 0 }}">

                            <button type="submit" class="btn btn-primary">
                                {{ __('import.confirm', ['count' => count($preview)]) }}
                            </button>

                            <a href="{{ route('projects.tasks.import', $project) }}" class="btn btn-ghost">
                                {{ __('common.cancel') }}
                            </a>

                            @if ($replace)
                                <span class="badge badge-warn">{{ __('import.will_replace') }}</span>
                            @endif
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('import.format_title') }}</h2></div>
                <div class="space-y-2 p-4 text-xs text-slate-600">
                    <p>{{ __('import.format_help') }}</p>

                    <table class="w-full border-collapse text-[11px]">
                        <thead>
                            <tr class="border-b border-slate-200 text-left">
                                <th class="py-1 pr-2 font-semibold">Nombre</th>
                                <th class="py-1 pr-2 font-semibold">Duración</th>
                                <th class="py-1 pr-2 font-semibold">Nivel</th>
                                <th class="py-1 font-semibold">Depende de</th>
                            </tr>
                        </thead>
                        <tbody class="font-mono">
                            <tr class="border-b border-slate-100"><td class="py-1 pr-2">Análisis</td><td>0</td><td>0</td><td>—</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1 pr-2">Entrevistas</td><td>3d</td><td>1</td><td>—</td></tr>
                            <tr><td class="py-1 pr-2">Documento</td><td>4d</td><td>1</td><td>2</td></tr>
                        </tbody>
                    </table>

                    <p class="border-t border-slate-100 pt-2">{{ __('import.separator_help') }}</p>
                </div>
            </section>
        </aside>
    </div>
@endsection
