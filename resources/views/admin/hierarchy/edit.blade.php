@extends('layouts.app')

@section('title', __('hierarchy.title'))
@section('heading', __('hierarchy.assign_heading', ['name' => $user->name]))

@section('content')
    <form method="POST" action="{{ route('admin.hierarchy.update', $user) }}"
          class="max-w-2xl space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200">
        @csrf
        @method('PUT')

        <dl class="flex gap-2 text-sm">
            <dt class="text-slate-600">{{ __('hierarchy.current') }}:</dt>
            <dd class="font-medium text-slate-900">{{ $manager?->name ?? __('hierarchy.none') }}</dd>
        </dl>

        <div class="space-y-1">
            <label for="manager-field" class="block text-sm font-medium text-slate-700">
                {{ __('hierarchy.manager') }}
            </label>
            <select id="manager-field" name="manager_id"
                    @if ($errors->has('manager_id')) aria-invalid="true" aria-describedby="manager-field-error" @endif
                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600 sm:text-sm">
                <option value="">{{ __('hierarchy.no_manager') }}</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->id }}"
                            @selected((int) old('manager_id', $manager?->id ?? 0) === $candidate->id)>
                        {{ $candidate->name }}
                    </option>
                @endforeach
            </select>
            @error('manager_id')
                <p id="manager-field-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <p class="rounded-md bg-slate-50 px-4 py-3 text-xs text-slate-600 ring-1 ring-slate-200">
            {{ __('hierarchy.history_note') }}
        </p>

        <div class="flex gap-3">
            <button type="submit"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.hierarchy.index') }}"
               class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
