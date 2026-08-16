@extends('layouts.app')

@section('title', __('org_units.new'))
@section('heading', __('org_units.new'))

@section('content')
    <form method="POST" action="{{ route('admin.org-units.store') }}" class="max-w-2xl rounded-lg bg-white p-6 ring-1 ring-slate-200">
        @csrf

        @include('admin.org-units._form')

        <div class="mt-6 flex gap-3">
            <button type="submit"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('common.create') }}
            </button>
            <a href="{{ route('admin.org-units.index') }}"
               class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
