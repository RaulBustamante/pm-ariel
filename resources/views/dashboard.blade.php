@extends('layouts.app')

@section('title', __('common.dashboard'))
@section('heading', __('common.dashboard'))

@section('content')
    {{-- Estado vacío con guía: qué es esto, por qué está vacío y qué sigue.
         La Etapa 3 sustituye este bloque por las vistas reales del proyecto. --}}
    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
        <h2 class="text-base font-semibold text-slate-900">{{ __('dashboard.empty_title') }}</h2>
        <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600">{{ __('dashboard.empty_body') }}</p>

        @can('viewAny', App\Models\User::class)
            <a href="{{ route('admin.users.index') }}"
               class="mt-6 inline-block rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('dashboard.empty_action') }}
            </a>
        @endcan
    </div>
@endsection
