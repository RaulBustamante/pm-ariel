@php
    // El filtro viaja en la dirección, así que se arrastra a cada pestaña: si
    // cada vista tuviera el suyo, la gente filtraría en la Lista, saltaría al
    // Gantt, vería todo otra vez y concluiría que el filtro no sirve.
    $carry = isset($filter) ? $filter->toQuery() : [];

    $tabs = [
        'dashboard' => ['route' => 'projects.dashboard', 'label' => __('dashboard.title')],
        'list' => ['route' => 'projects.tasks.index', 'label' => __('tasks.list_view')],
        'gantt' => ['route' => 'projects.gantt', 'label' => __('tasks.gantt_view')],
        'kanban' => ['route' => 'projects.kanban', 'label' => __('kanban.title')],
        'calendar' => ['route' => 'projects.calendar', 'label' => __('calendar.title')],
        'resources' => ['route' => 'projects.resources.index', 'label' => __('resources.title')],
        'analysis' => ['route' => 'projects.analysis', 'label' => __('analysis.title')],
        'advisor' => ['route' => 'projects.advisor', 'label' => __('advisor.title')],
        'documents' => ['route' => 'projects.documents', 'label' => __('documents.title')],
    ];

    if (auth()->user()?->can('update', $project)) {
        $tabs['settings'] = ['route' => 'projects.edit', 'label' => __('projects.settings')];
    }
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200">
    <nav data-shortcut-nav class="flex gap-1 overflow-x-auto" aria-label="{{ __('tasks.title') }}">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], ['project' => $project, ...$carry]) }}"
               data-shortcut-to="{{ $key }}"
               @if ($active === $key) aria-current="page" @endif
               class="-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition-colors
                      {{ $active === $key
                            ? 'border-brand-700 text-brand-800'
                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="mb-1 flex items-center gap-3">
        <a href="{{ route('projects.initiation.overview', $project) }}"
           class="rounded text-sm text-slate-600 underline hover:text-slate-900">
            {{ __('initiation.title') }}
        </a>

        @include('partials._shortcuts')
    </div>
</div>
