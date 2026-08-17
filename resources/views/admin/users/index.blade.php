@extends('layouts.app')

@section('title', __('users.title'))
@section('heading', __('users.title'))

@section('content')
    @can('create', App\Models\User::class)
        <div class="mb-4">
            <a href="{{ route('admin.users.create') }}"
               class="inline-block rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('users.create_action') }}
            </a>
        </div>
    @endcan

    @if ($users->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-surface p-8 text-center">
            <h2 class="text-base font-semibold">{{ __('common.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">{{ __('common.empty_users') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('common.empty_action') }}</p>
        </div>
    @else
        {{-- La tabla desborda en su propio contenedor: el cuerpo de la página
             nunca hace scroll horizontal. --}}
        <div class="overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('users.title') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('common.name') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('common.email') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('common.org_unit') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('users.roles') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('common.status') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->orgUnit?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $user->roles->pluck('name')->implode(', ') ?: __('users.no_roles') }}
                            </td>
                            <td class="px-4 py-3">
                                {{-- Estado con texto además del color: nada se comunica solo con color. --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $user->is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                    <span aria-hidden="true">{{ $user->is_active ? '●' : '○' }}</span>
                                    {{ $user->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="rounded text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                        {{ __('common.edit') }}<span class="sr-only"> — {{ $user->name }}</span>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    @endif
@endsection
