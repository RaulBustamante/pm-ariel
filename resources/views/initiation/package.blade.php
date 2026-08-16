<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->code }} · {{ __('initiation.package') }} · {{ config('branding.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /* La hoja de impresión es parte del entregable, no un extra: este
           documento existe para presentarse en papel o en PDF ante dirección. */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            section { break-inside: avoid; }
            h2 { break-after: avoid; }
            table { break-inside: auto; }
            tr { break-inside: avoid; }
            a[href]::after { content: ""; }
        }
        @page { margin: 18mm 14mm; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="no-print sticky top-0 z-10 border-b border-slate-200 bg-white px-4 py-3">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3">
            <a href="{{ route('projects.initiation.overview', $project) }}"
               class="rounded text-sm text-blue-700 underline focus:outline-none focus:ring-2 focus:ring-blue-600">
                ← {{ __('initiation.title') }}
            </a>
            <p class="text-xs text-slate-600">{{ __('initiation.print_hint') }}</p>
            <button type="button" onclick="window.print()"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('initiation.download_package') }}
            </button>
        </div>
    </div>

    <main class="sheet mx-auto my-6 max-w-4xl bg-white p-10 shadow">
        <header class="border-b-2 border-slate-900 pb-4">
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ __('initiation.package') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $project->name }}</h1>
            <p class="mt-1 font-mono text-sm text-slate-600">{{ $project->code }}</p>

            <dl class="mt-4 grid grid-cols-2 gap-x-8 gap-y-1 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-slate-500">{{ __('common.org_unit') }}</dt>
                    <dd class="font-medium">{{ $project->orgUnit?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('initiation.prepared_by') }}</dt>
                    <dd class="font-medium">{{ $project->owner?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('initiation.field_sponsor') }}</dt>
                    <dd class="font-medium">{{ $charter?->sponsor?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('common.status') }}</dt>
                    <dd class="font-medium">
                        {{ $charter?->isApproved()
                            ? __('initiation.approved_on', [
                                'date' => $charter->approved_at->format('d/m/Y'),
                                'name' => $charter->approver?->name ?? '—',
                              ])
                            : __('initiation.not_approved') }}
                    </dd>
                </div>
            </dl>
        </header>

        @php
            $sections = [
                'problem_statement', 'opportunity', 'expected_benefit', 'alignment',
                'objectives', 'deliverables', 'success_criteria',
                'out_of_scope', 'assumptions', 'constraints', 'high_level_milestones',
            ];
        @endphp

        @foreach ($sections as $field)
            @if ($charter !== null && filled($charter->{$field}))
                <section class="mt-6">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">
                        {{ __("initiation.field_{$field}") }}
                    </h2>
                    <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed">{{ $charter->{$field} }}</p>
                </section>
            @endif
        @endforeach

        @if ($project->stakeholders->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">
                    {{ __('initiation.step_stakeholders_title') }}
                </h2>

                <table class="mt-2 w-full border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-300 text-left">
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.stakeholder_name') }}</th>
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.stakeholder_role') }}</th>
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.stakeholder_power') }}</th>
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.stakeholder_interest') }}</th>
                            <th scope="col" class="py-1.5 font-semibold">{{ __('initiation.stakeholder_strategy') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($project->stakeholders as $person)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="py-1.5 pr-3 font-medium">{{ $person->name }}</td>
                                <td class="py-1.5 pr-3">{{ collect([$person->role_title, $person->organization])->filter()->implode(' · ') ?: '—' }}</td>
                                <td class="py-1.5 pr-3">{{ $person->power }}</td>
                                <td class="py-1.5 pr-3">{{ $person->interest }}</td>
                                <td class="py-1.5">
                                    <span class="font-medium">{{ __("initiation.quadrant_{$person->quadrant()}") }}.</span>
                                    {{ $person->engagement_strategy }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($project->risks->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">
                    {{ __('initiation.step_risks_title') }}
                </h2>

                <table class="mt-2 w-full border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-300 text-left">
                            <th scope="col" class="py-1.5 pr-2 font-semibold">{{ __('initiation.risk_code') }}</th>
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.risk_description') }}</th>
                            <th scope="col" class="py-1.5 pr-2 font-semibold">{{ __('initiation.risk_score') }}</th>
                            <th scope="col" class="py-1.5 pr-3 font-semibold">{{ __('initiation.risk_owner') }}</th>
                            <th scope="col" class="py-1.5 font-semibold">{{ __('initiation.add_response') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($project->risks as $risk)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="py-1.5 pr-2 font-mono">{{ $risk->code }}</td>
                                <td class="py-1.5 pr-3">
                                    {{ $risk->description }}
                                    @if ($risk->kind === \App\Models\Risk::KIND_OPPORTUNITY)
                                        <span class="font-medium">({{ __('initiation.risk_kind_opportunity') }})</span>
                                    @endif
                                </td>
                                <td class="py-1.5 pr-2 whitespace-nowrap">
                                    {{ __("initiation.level_{$risk->level()}") }} ({{ $risk->score() }})
                                </td>
                                <td class="py-1.5 pr-3">{{ $risk->owner?->name ?? '—' }}</td>
                                <td class="py-1.5">
                                    @forelse ($risk->responses as $response)
                                        <p>{{ __("initiation.strategy_{$response->strategy}") }} — {{ $response->description }}</p>
                                    @empty
                                        <p class="text-slate-500">{{ __('initiation.no_responses') }}</p>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($findings !== [])
            {{-- Lo que falta se imprime con el documento a propósito. Un paquete
                 incompleto que se presenta como completo es peor que uno que
                 dice, en su última hoja, qué le falta todavía. --}}
            <section class="mt-8 border-t border-dashed border-slate-300 pt-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ __('initiation.health') }}</h2>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($findings as $finding)
                        <li>
                            <span class="font-medium">{{ $finding->isBlocking() ? '!' : '·' }} {{ $finding->message }}</span>
                            <span class="text-slate-600">{{ $finding->why }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <footer class="mt-10 border-t border-slate-200 pt-3 text-xs text-slate-500">
            {{ __('initiation.generated_on', ['date' => now()->format('d/m/Y H:i')]) }} ·
            {{ config('branding.name') }}
        </footer>
    </main>
</body>
</html>
