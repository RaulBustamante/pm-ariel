{{-- La hoja de referencia de los atajos.
     Un atajo sin dónde consultarlo es una función que solo conoce quien la
     programó. Se abre con «?» y también con clic, para no depender del atajo
     que precisamente se está explicando. --}}
<details data-shortcut-sheet class="relative">
    <summary class="cursor-pointer list-none rounded border border-slate-300 px-1.5 py-0.5 text-xs font-medium text-slate-600 hover:border-brand-600 hover:text-brand-700">
        <span aria-hidden="true">?</span>
        <span class="sr-only">{{ __('shortcuts.title') }}</span>
    </summary>

    <div class="absolute right-0 top-7 z-30 w-72 rounded-lg border border-slate-200 bg-surface p-3 shadow-raised">
        <p class="text-xs font-semibold text-slate-900">{{ __('shortcuts.title') }}</p>

        <dl class="mt-2 space-y-1">
            @foreach ([
                'd' => __('dashboard.title'),
                'l' => __('tasks.list_view'),
                'g' => __('tasks.gantt_view'),
                'k' => __('kanban.title'),
                'c' => __('calendar.title'),
                'a' => __('advisor.title'),
                '?' => __('shortcuts.this_sheet'),
            ] as $key => $label)
                <div class="flex items-center justify-between gap-2 text-xs">
                    <dt class="text-slate-600">{{ $label }}</dt>
                    <dd>
                        <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 font-mono text-[11px] text-slate-700">
                            {{ $key }}
                        </kbd>
                    </dd>
                </div>
            @endforeach
        </dl>

        <p class="mt-2 border-t border-slate-100 pt-2 text-[11px] leading-relaxed text-slate-500">
            {{ __('shortcuts.help') }}
        </p>
    </div>
</details>
