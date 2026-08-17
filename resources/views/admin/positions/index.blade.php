@extends('layouts.app')

@section('title', __('positions.title'))
@section('heading', __('positions.title'))

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-3xl text-sm text-slate-600">{{ __('positions.intro') }}</p>

        <a href="{{ route('admin.positions.create') }}" class="btn btn-primary shrink-0">
            {{ __('positions.create') }}
        </a>
    </div>

    @if ($positions->isEmpty())
        {{-- Estado vacío con guía. Este caso existía de verdad: el desplegable
             del alta de usuarios llevaba cinco etapas vacío porque nunca hubo
             pantalla para llenarlo, y un desplegable vacío no se distingue de
             uno cuyas opciones no aplican. --}}
        <div class="card p-8 text-center">
            <h2 class="text-base font-semibold text-slate-900">{{ __('positions.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-600">{{ __('positions.empty_body') }}</p>

            <a href="{{ route('admin.positions.create') }}" class="btn btn-primary mt-5">
                {{ __('positions.create') }}
            </a>
        </div>
    @else
        <section class="card hud-in">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <caption class="sr-only">{{ __('positions.title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="w-20">{{ __('positions.level') }}</th>
                            <th scope="col">{{ __('positions.name') }}</th>
                            <th scope="col" class="w-28">{{ __('positions.people') }}</th>
                            <th scope="col" class="w-40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($positions as $position)
                            <tr>
                                <td class="font-mono text-xs text-slate-500">{{ $position->level }}</td>
                                <td class="font-medium text-slate-900">{{ $position->name }}</td>
                                <td>
                                    <span class="badge {{ $position->users_count > 0 ? 'badge-brand' : 'badge-neutral' }}">
                                        {{ $position->users_count }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('admin.positions.edit', $position) }}" class="btn btn-secondary btn-sm">
                                            {{ __('common.edit') }}
                                        </a>

                                        {{-- Solo se ofrece borrar lo que se puede borrar. El
                                             controlador lo vuelve a comprobar: la lista es
                                             cortesía, no la defensa. --}}
                                        @if ($position->users_count === 0)
                                            <form method="POST" action="{{ route('admin.positions.destroy', $position) }}"
                                                  onsubmit="return confirm('{{ __('positions.confirm_delete') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
