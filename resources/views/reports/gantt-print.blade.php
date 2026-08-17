<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->code }} · {{ __('tasks.gantt_view') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /*
        | El Gantt se imprime desde el navegador y no se genera con dompdf
        | (D-020): dompdf no dibuja SVG de forma confiable, y la alternativa
        | —instalar un navegador headless en el servidor— es una decisión de TI.
        */
        @page { size: letter landscape; margin: 12mm; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }

            /* Cada bloque de tareas es una hoja, y **cada hoja repite el
               encabezado**. Un Gantt de ocho páginas donde solo la primera trae
               los nombres es un Gantt que no se puede leer. */
            .hoja { page-break-after: always; }
            .hoja:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body class="bg-slate-100">
    <div class="no-print sticky top-0 z-10 border-b border-slate-200 bg-white px-4 py-3">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <a href="{{ route('projects.gantt', $project) }}" class="text-sm text-brand-700 underline">
                ← {{ __('tasks.gantt_view') }}
            </a>
            <p class="text-xs text-slate-600">
                {{ __('reports.print_landscape') }} · {{ count($pages) }} {{ mb_strtolower(__('reports.pages')) }}
            </p>
            <button type="button" onclick="window.print()" class="btn btn-primary">
                {{ __('reports.print') }}
            </button>
        </div>
    </div>

    <main class="mx-auto max-w-6xl space-y-4 p-4">
        @foreach ($pages as $pageIndex => $pageTasks)
            @php
                // El trazo se recalcula por hoja pero con la misma ventana de
                // tiempo del proyecto completo: si cada hoja tuviera su propia
                // escala, las barras de la página 2 no se podrían comparar con
                // las de la 1.
                $layout = new \App\Support\Scheduling\GanttLayout($tasks, 'week');
            @endphp

            <section class="hoja bg-white p-4 shadow-sm">
                <header class="mb-2 flex items-baseline justify-between border-b border-slate-300 pb-2">
                    <div>
                        <h1 class="text-base font-bold">{{ $project->name }}</h1>
                        <p class="font-mono text-xs text-slate-500">{{ $project->code }}</p>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ __('reports.page') }} {{ $pageIndex + 1 }}/{{ count($pages) }} ·
                        {{ now()->format('d/m/Y') }}
                    </p>
                </header>

                <div class="flex">
                    <div class="w-56 shrink-0 border-r border-slate-200">
                        <div class="h-[42px] border-b border-slate-200 bg-slate-50 px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                            {{ __('tasks.name') }}
                        </div>
                        @foreach ($pageTasks as $task)
                            <div class="flex h-[26px] items-center gap-1 truncate border-b border-slate-50 px-2 text-[10px]
                                        {{ $task->is_summary ? 'font-semibold' : '' }}"
                                 style="padding-left: {{ 0.5 + ($task->outline_depth ?? 0) * 0.6 }}rem">
                                @if ($task->is_critical && ! $task->is_summary)
                                    <span aria-hidden="true" class="text-red-600">●</span>
                                @endif
                                {{ $task->name }}
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-hidden">
                        @include('reports._gantt-page', [
                            'layout' => $layout,
                            'pageTasks' => $pageTasks,
                            'allTasks' => $tasks,
                            'dependencies' => $dependencies,
                        ])
                    </div>
                </div>

                <footer class="mt-2 flex gap-4 border-t border-slate-200 pt-2 text-[10px] text-slate-600">
                    <span><span class="inline-block h-2 w-4 rounded-sm bg-brand-600 align-middle"></span> {{ __('gantt.legend_task') }}</span>
                    <span><span class="inline-block h-2 w-4 rounded-sm bg-red-600 align-middle"></span> {{ __('tasks.critical') }}</span>
                    <span><span class="inline-block h-2 w-4 bg-slate-700 align-middle"></span> {{ __('tasks.summary') }}</span>
                </footer>
            </section>
        @endforeach
    </main>
</body>
</html>
