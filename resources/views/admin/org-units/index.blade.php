@extends('layouts.app')

@section('title', __('org_units.title'))
@section('heading', __('org_units.heading'))

@section('content')
    <p class="mb-4 max-w-2xl text-sm text-slate-600">{{ __('org_units.intro') }}</p>

    @if (session('error'))
        <div role="alert" class="mb-6 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            {{ session('error') }}
        </div>
    @endif

    @can('create', App\Models\OrgUnit::class)
        <div class="mb-4">
            <a href="{{ route('admin.org-units.create') }}"
               class="inline-block rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('org_units.new') }}
            </a>
        </div>
    @endcan

    @if ($tree->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-surface p-8 text-center">
            <h2 class="text-base font-semibold">{{ __('common.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">{{ __('org_units.empty') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('org_units.heading') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('org_units.name') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('org_units.code') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('org_units.people') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tree as $unit)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{-- La sangría se lee con la vista; el nivel va aparte
                                     para quien usa lector de pantalla. --}}
                                <span style="padding-left: {{ $unit->depth * 1.25 }}rem" class="inline-block">
                                    @if ($unit->depth > 0)
                                        <span aria-hidden="true" class="text-slate-400">└</span>
                                    @endif
                                    {{ $unit->name }}
                                </span>
                                <span class="sr-only"> — {{ __('org_units.level') }} {{ $unit->depth + 1 }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $unit->code ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $unit->users_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $unit)
                                        <a href="{{ route('admin.org-units.edit', $unit) }}"
                                           class="rounded text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                            {{ __('common.edit') }}<span class="sr-only"> — {{ $unit->name }}</span>
                                        </a>
                                    @endcan

                                    @can('delete', $unit)
                                        <form method="POST" action="{{ route('admin.org-units.destroy', $unit) }}"
                                              onsubmit="return confirm('{{ __('org_units.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="rounded text-red-700 underline hover:text-red-900 focus:outline-none focus:ring-2 focus:ring-red-600">
                                                {{ __('common.delete') }}<span class="sr-only"> — {{ $unit->name }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
