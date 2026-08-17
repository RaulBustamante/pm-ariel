@php
    /** @var \App\Support\Scheduling\TaskFilter $filter */
    $route = $filterRoute ?? 'projects.tasks.index';
@endphp

<form method="GET" action="{{ route($route, $project) }}"
      class="mb-4 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-surface px-3 py-2.5">

    <div class="min-w-[12rem] flex-1">
        <label for="filter-q" class="sr-only">{{ __('filters.search') }}</label>
        <input id="filter-q" type="search" name="q" value="{{ $filter->search }}"
               placeholder="{{ __('filters.search') }}" class="field">
    </div>

    <div>
        <label for="filter-progress" class="sr-only">{{ __('filters.progress') }}</label>
        <select id="filter-progress" name="progress" class="field">
            @foreach (['all', 'todo', 'doing', 'done'] as $option)
                <option value="{{ $option }}" @selected($filter->progress === $option)>
                    {{ __("filters.progress_{$option}") }}
                </option>
            @endforeach
        </select>
    </div>

    <label class="flex items-center gap-1.5 whitespace-nowrap px-1 text-sm text-slate-700">
        <input type="checkbox" name="critical" value="1" @checked($filter->onlyCritical)
               class="rounded border-slate-300 text-brand-700">
        {{ __('filters.only_critical') }}
    </label>

    <label class="flex items-center gap-1.5 whitespace-nowrap px-1 text-sm text-slate-700">
        <input type="checkbox" name="mine" value="1" @checked($filter->onlyMine)
               class="rounded border-slate-300 text-brand-700">
        {{ __('filters.only_mine') }}
    </label>

    <button type="submit" class="btn btn-secondary btn-sm">{{ __('filters.apply') }}</button>

    @if ($filter->isActive())
        <a href="{{ route($route, $project) }}" class="btn btn-ghost btn-sm">{{ __('filters.clear') }}</a>
        <span class="badge badge-brand">{{ __('filters.active', ['count' => $visibleCount ?? 0]) }}</span>
    @endif
</form>
