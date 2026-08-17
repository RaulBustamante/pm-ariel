@extends('layouts.app')

@section('title', __('resources.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'resources'])

    {{-- Los cuatro números del costo, arriba.
         «¿Cuánto cuesta este proyecto?» no es una pregunta sino cuatro, y un
         total solo no contesta ninguna: hace falta saber cuánto es gente, cuánto
         material y cuánto ya se consumió. --}}
    <section class="card card-hud hud-in mb-4 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['total', $costs['total'], null],
                ['earned', $costs['earned'], $costs['total']],
                ['labor', $costs['labor'], $costs['total']],
                ['materials', $costs['materials'], $costs['total']],
            ] as [$key, $value, $against])
                <div>
                    <p class="stat-label">{{ __("resources.cost_{$key}") }}</p>
                    <p class="stat-value">{{ number_format($value, 0) }}<span class="stat-unit"> {{ __('resources.currency') }}</span></p>

                    @if ($against !== null && $against > 0)
                        <div class="meter mt-2 h-1.5">
                            <div class="meter-fill" style="width: {{ min(100, round($value / $against * 100)) }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{ round($value / $against * 100) }} % {{ __('resources.of_total') }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-3 text-xs text-slate-600">
            <span>{{ __('resources.cost_fixed') }}: <strong class="text-slate-900">{{ number_format($costs['fixed'], 2) }}</strong></span>
            <span>{{ __('resources.cost_external') }}: <strong class="text-slate-900">{{ number_format($costs['external'], 2) }}</strong></span>
            <span>{{ __('resources.total_hours') }}: <strong class="text-slate-900">{{ number_format($costs['hours'], 1) }}</strong></span>
        </div>

        {{-- Un recurso sin tarifa aporta cero, y un cero no se distingue de «es
             gratis». Quien lea un total tiene derecho a saber que hay huecos. --}}
        @if ($costs['missing_rates'] !== [])
            <p class="badge-warn mt-3 block rounded-md border px-3 py-2 text-xs">
                {{ __('resources.missing_rates', ['names' => implode(', ', array_slice($costs['missing_rates'], 0, 6))]) }}
            </p>
        @endif
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <section class="card hud-in hud-in-1">
                <div class="card-header">
                    <h2 class="card-title">{{ __('resources.title') }}</h2>
                    <span class="text-xs text-slate-500">{{ $resources->count() }}</span>
                </div>

                @if ($resources->isEmpty())
                    <p class="p-5 text-center text-sm text-slate-500">{{ __('resources.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <caption class="sr-only">{{ __('resources.title') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('resources.name') }}</th>
                                    <th scope="col" class="w-28">{{ __('resources.type') }}</th>
                                    <th scope="col" class="w-32 text-right">{{ __('resources.rate') }}</th>
                                    <th scope="col" class="w-20 text-right">{{ __('resources.assigned_to') }}</th>
                                    <th scope="col" class="w-24"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resources as $row)
                                    <tr>
                                        <td>
                                            <span class="font-medium text-slate-900">{{ $row->name }}</span>
                                            @if ($row->is_external)
                                                <span class="badge badge-warn ml-1">{{ __('resources.external') }}</span>
                                            @endif
                                            @if ($row->role_title || $row->supplier)
                                                <span class="block text-[11px] text-slate-500">
                                                    {{ collect([$row->role_title, $row->supplier])->filter()->implode(' · ') }}
                                                </span>
                                            @endif
                                        </td>

                                        <td>
                                            <span class="badge {{ $row->isMaterial() ? 'badge-neutral' : 'badge-brand' }}">
                                                {{ __("resources.type_{$row->type}") }}
                                            </span>
                                        </td>

                                        {{-- La tarifa se muestra en la unidad de cada tipo. Poner
                                             «por hora» a un material sería un dato falso. --}}
                                        <td class="text-right tabular">
                                            @if ($row->isMaterial())
                                                @if ($row->cost_per_unit !== null)
                                                    {{ number_format((float) $row->cost_per_unit, 2) }}
                                                    <span class="text-[11px] text-slate-500">/ {{ $row->unit_of_measure ?: '?' }}</span>
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            @else
                                                @if ($row->cost_per_hour !== null)
                                                    {{ number_format((float) $row->cost_per_hour, 2) }}
                                                    <span class="text-[11px] text-slate-500">/ h</span>
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            @endif
                                        </td>

                                        <td class="text-right tabular text-slate-600">{{ $row->assignments_count }}</td>

                                        <td>
                                            @can('update', $project)
                                                <a href="{{ route('projects.resources.edit', [$project, $row]) }}"
                                                   class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($costs['by_resource'] !== [])
                <section class="card hud-in hud-in-2">
                    <div class="card-header"><h2 class="card-title">{{ __('resources.cost_by_resource') }}</h2></div>

                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <caption class="sr-only">{{ __('resources.cost_by_resource') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('resources.name') }}</th>
                                    <th scope="col" class="w-24 text-right">{{ __('resources.hours') }}</th>
                                    <th scope="col" class="w-32 text-right">{{ __('resources.cost') }}</th>
                                    <th scope="col" class="w-40">{{ __('resources.share') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($costs['by_resource'] as $line)
                                    @php $share = $costs['total'] > 0 ? $line['cost'] / $costs['total'] * 100 : 0; @endphp
                                    <tr>
                                        <td>
                                            {{ $line['name'] }}
                                            <span class="ml-1 text-[11px] text-slate-500">{{ __("resources.type_{$line['type']}") }}</span>
                                        </td>
                                        <td class="text-right tabular">{{ $line['hours'] > 0 ? number_format($line['hours'], 1) : '—' }}</td>
                                        <td class="text-right tabular font-medium text-slate-900">{{ number_format($line['cost'], 2) }}</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="meter h-1.5 flex-1"><div class="meter-fill" style="width: {{ round($share) }}%"></div></div>
                                                <span class="w-10 shrink-0 text-right text-[11px] tabular text-slate-500">{{ round($share) }} %</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-4">
            @can('update', $project)
                {{-- El formulario cambia según el tipo. Los campos de horas y los
                     de unidad no aplican al mismo recurso, y mostrar los cuatro
                     siempre invita a llenar el que no corresponde. --}}
                <section class="card hud-in hud-in-3 p-4">
                    <h2 class="card-title mb-3">
                        {{ $resource ? __('resources.edit') : __('resources.add') }}
                    </h2>

                    <form method="POST"
                          action="{{ $resource ? route('projects.resources.update', [$project, $resource]) : route('projects.resources.store', $project) }}"
                          class="space-y-3"
                          data-resource-form>
                        @csrf
                        @if ($resource) @method('PUT') @endif

                        <x-form-field name="name" :label="__('resources.name')" :value="$resource?->name" required />

                        <div>
                            <label for="type" class="field-label">{{ __('resources.type') }}</label>
                            <select id="type" name="type" class="field" data-resource-type>
                                @foreach (\App\Models\Resource::types() as $type)
                                    <option value="{{ $type }}"
                                            @selected(old('type', $resource?->type ?? \App\Models\Resource::TYPE_PERSON) === $type)>
                                        {{ __("resources.type_{$type}") }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-help">{{ __('resources.type_help') }}</p>
                        </div>

                        {{-- Solo para lo que aporta horas --}}
                        <div class="space-y-3" data-when-work>
                            <x-form-field name="role_title" :label="__('resources.role_title')" :value="$resource?->role_title" />

                            <x-form-field name="capacity_percent" type="number"
                                          :label="__('resources.capacity')"
                                          :value="$resource?->capacity_percent ?? 100"
                                          :help="__('resources.capacity_help')" />

                            <x-form-field name="cost_per_hour" type="number" step="0.01"
                                          :label="__('resources.cost_per_hour')"
                                          :value="$resource?->cost_per_hour"
                                          :help="__('resources.cost_optional')" />
                        </div>

                        {{-- Solo para lo que se consume --}}
                        <div class="space-y-3" data-when-material hidden>
                            <x-form-field name="unit_of_measure"
                                          :label="__('resources.unit')"
                                          :value="$resource?->unit_of_measure"
                                          :help="__('resources.unit_help')" />

                            <x-form-field name="cost_per_unit" type="number" step="0.01"
                                          :label="__('resources.cost_per_unit')"
                                          :value="$resource?->cost_per_unit"
                                          :help="__('resources.cost_optional')" />
                        </div>

                        <x-form-field name="supplier" :label="__('resources.supplier')" :value="$resource?->supplier" />

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="is_external" value="1"
                                   @checked(old('is_external', $resource?->is_external))
                                   class="mt-0.5 rounded border-slate-300">
                            <span>
                                <span class="font-medium text-slate-900">{{ __('resources.is_external') }}</span>
                                <span class="mt-0.5 block text-xs text-slate-600">{{ __('resources.is_external_help') }}</span>
                            </span>
                        </label>

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                            @if ($resource)
                                <a href="{{ route('projects.resources.index', $project) }}" class="btn btn-secondary">
                                    {{ __('common.cancel') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </section>
            @endcan

            @if ($costs['by_type'] !== [])
                <section class="card hud-in hud-in-4 p-4">
                    <h2 class="card-title mb-3">{{ __('resources.cost_by_type') }}</h2>

                    <dl class="space-y-2.5">
                        @foreach ($costs['by_type'] as $index => $line)
                            <div>
                                <div class="flex justify-between text-xs">
                                    <dt class="text-slate-600">
                                        {{ $line['type'] === 'fixed' ? __('resources.cost_fixed') : __("resources.type_{$line['type']}") }}
                                    </dt>
                                    <dd class="tabular font-medium text-slate-900">{{ number_format($line['cost'], 2) }}</dd>
                                </div>
                                <div class="meter mt-1 h-1.5">
                                    {{-- La paleta categórica: cada tipo con su color, distinguible
                                         también en escala de grises. --}}
                                    <div class="h-full rounded-full"
                                         style="width: {{ $line['share'] }}%; background: var(--color-viz-{{ ($index % 8) + 1 }})"></div>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    {{-- Enseña y esconde los campos que no aplican al tipo elegido.
         Es **solo comodidad**: el servidor decide de verdad qué se guarda, así
         que sin JavaScript el formulario sigue funcionando —se ven los cuatro
         campos y el que no aplica se ignora. --}}
    <script>
        document.querySelectorAll('[data-resource-form]').forEach((form) => {
            const select = form.querySelector('[data-resource-type]');
            const work = form.querySelector('[data-when-work]');
            const material = form.querySelector('[data-when-material]');

            const apply = () => {
                const isMaterial = select.value === 'material';
                work.hidden = isMaterial;
                material.hidden = !isMaterial;
            };

            select.addEventListener('change', apply);
            apply();
        });
    </script>
@endpush
