@extends('layouts.app')

@section('title', __('hierarchy.title'))
@section('heading', __('hierarchy.heading'))

@section('content')
    <p class="mb-4 max-w-2xl text-sm text-slate-600">{{ __('hierarchy.intro') }}</p>

    @if ($roots->isNotEmpty())
        {{-- Quién no reporta a nadie es la pregunta que más rápido delata un
             organigrama a medio capturar. Va arriba, no escondida en la tabla. --}}
        <div class="mb-6 rounded-md bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200">
            <p class="font-medium text-slate-900">
                {{ __('hierarchy.roots') }} ({{ $roots->count() }})
            </p>
            <p class="mt-1 text-slate-600">{{ __('hierarchy.roots_help') }}</p>
            <p class="mt-2 text-slate-700">{{ $roots->pluck('name')->implode(' · ') }}</p>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <caption class="sr-only">{{ __('hierarchy.heading') }}</caption>
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ __('hierarchy.person') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('common.org_unit') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('hierarchy.manager') }}</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('common.actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $person)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $person->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $person->orgUnit?->name ?? '—' }}</td>
                        <td class="px-4 py-3 {{ $managers->has($person->id) ? 'text-slate-600' : 'text-[var(--color-badge-warn-fg)]' }}">
                            {{ $managers->get($person->id)?->name ?? __('hierarchy.no_manager') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $person)
                                <a href="{{ route('admin.hierarchy.edit', $person) }}"
                                   class="rounded text-brand-700 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-hud-500">
                                    {{ __('hierarchy.change') }}<span class="sr-only"> — {{ $person->name }}</span>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
