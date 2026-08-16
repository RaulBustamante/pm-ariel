@extends('layouts.app')

@section('title', __('common.audit_log'))
@section('heading', __('common.audit_log'))

@section('content')
    @if ($logs->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="text-base font-semibold">{{ __('common.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">{{ __('audit.empty_body') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('common.audit_log') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('audit.when') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('audit.who') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('audit.what') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('audit.event') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('audit.changes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $log->created_at?->timezone(auth()->user()->timezone)->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">{{ $log->user?->name ?? __('audit.system') }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                            </td>
                            <td class="px-4 py-3">{{ __('audit.events.'.$log->event) }}</td>
                            <td class="px-4 py-3">
                                @if ($log->new_values)
                                    <details>
                                        <summary class="cursor-pointer text-blue-700 underline focus:outline-none focus:ring-2 focus:ring-blue-600">
                                            {{ __('audit.see_detail') }}
                                        </summary>
                                        <dl class="mt-2 space-y-1 text-xs">
                                            @foreach ($log->new_values as $field => $value)
                                                <div class="flex gap-2">
                                                    <dt class="font-medium">{{ $field }}:</dt>
                                                    <dd class="text-slate-600">
                                                        <span class="line-through">{{ data_get($log->old_values, $field) ?? '—' }}</span>
                                                        <span aria-hidden="true">→</span>
                                                        {{ is_scalar($value) ? $value : json_encode($value) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
@endsection
