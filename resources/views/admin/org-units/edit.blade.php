@extends('layouts.app')

@section('title', __('org_units.edit'))
@section('heading', __('org_units.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.org-units.update', $unit) }}" class="max-w-2xl rounded-lg bg-surface p-6 ring-1 ring-slate-200">
        @csrf
        @method('PUT')

        @include('admin.org-units._form')

        <div class="mt-6 flex gap-3">
            <button type="submit"
                    class="btn btn-primary">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.org-units.index') }}"
               class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-hud-500">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
