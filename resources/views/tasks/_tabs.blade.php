@php
    $tabs = [
        'list' => ['route' => 'projects.tasks.index', 'label' => __('tasks.list_view')],
        'gantt' => ['route' => 'projects.gantt', 'label' => __('tasks.gantt_view')],
        'kanban' => ['route' => 'projects.kanban', 'label' => __('kanban.title')],
        'calendar' => ['route' => 'projects.calendar', 'label' => __('calendar.title')],
        'advisor' => ['route' => 'projects.advisor', 'label' => __('advisor.title')],
    ];

    if (auth()->user()?->can('update', $project)) {
        $tabs['settings'] = ['route' => 'projects.edit', 'label' => __('projects.settings')];
    }
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200">
    <nav class="flex gap-1" aria-label="{{ __('tasks.title') }}">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], $project) }}"
               @if ($active === $key) aria-current="page" @endif
               class="-mb-px border-b-2 px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-600
                      {{ $active === $key
                            ? 'border-blue-700 text-blue-800'
                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <a href="{{ route('projects.initiation.overview', $project) }}"
       class="mb-1 rounded text-sm text-slate-600 underline hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
        {{ __('initiation.title') }}
    </a>
</div>
