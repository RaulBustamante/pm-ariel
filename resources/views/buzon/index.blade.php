@extends('layouts.app')

@section('title', 'Buzón')
@section('heading', 'Buzón de mejoras')

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-xl font-semibold text-slate-900">Errores y sugerencias</h2><p class="mt-1 text-sm text-slate-600">Seguimiento de comentarios enviados desde el botón flotante.</p></div>
            <form method="GET" class="flex flex-wrap gap-2">
                <input name="q" value="{{ request('q') }}" class="input w-56" placeholder="Folio, título o descripción">
                <select name="tipo" class="input"><option value="">Todos los tipos</option>@foreach (\App\Models\BuzonTicket::tipos() as $value => $label)<option value="{{ $value }}" @selected(request('tipo') === $value)>{{ $label }}</option>@endforeach</select>
                <button class="btn btn-secondary">Filtrar</button>
            </form>
        </div>

        <div class="bz-board" data-buzon-board data-update-template="{{ route('admin.buzon.update', ['ticket' => '__ID__']) }}">
            @foreach ($estados as $estado => $label)
                @php $items = $tickets->get($estado, collect()); @endphp
                <section class="bz-board-column" data-buzon-column="{{ $estado }}">
                    <header><h3>{{ $label }}</h3><span>{{ $items->count() }}</span></header>
                    <div class="bz-board-list">
                        @forelse ($items as $ticket)
                            <article class="bz-ticket-card" draggable="true" data-ticket-id="{{ $ticket->id }}">
                                <div class="bz-ticket-meta"><span>{{ $ticket->folio }}</span><span class="badge {{ $ticket->tipo === 'error' ? 'badge-danger' : 'badge-warn' }}">{{ $ticket->tipo_label }}</span></div>
                                <h4>{{ $ticket->titulo }}</h4><p>{{ \Illuminate\Support\Str::limit($ticket->descripcion, 150) }}</p>
                                <dl>
                                    <div><dt>Reportó</dt><dd>{{ $ticket->user->name }}</dd></div><div><dt>Fecha</dt><dd>{{ $ticket->created_at->format('d/m/Y H:i') }}</dd></div>
                                    @if ($ticket->severidad_label)<div><dt>Impacto</dt><dd>{{ $ticket->severidad_label }}</dd></div>@endif
                                    @if ($ticket->navegador)<div><dt>Entorno</dt><dd>{{ $ticket->navegador }} · {{ $ticket->sistema_operativo }}</dd></div>@endif
                                </dl>
                                @if ($ticket->url)<a href="{{ $ticket->url }}" target="_blank" rel="noopener" class="bz-ticket-link">Abrir pantalla reportada ↗</a>@endif
                                @foreach ($ticket->adjuntos as $adjunto)<a href="{{ route('buzon.adjunto', $adjunto) }}" class="bz-ticket-link">Ver imagen · {{ $adjunto->tamanoLegible() }}</a>@endforeach
                                <details><summary>Gestionar</summary>
                                    <form method="POST" action="{{ route('admin.buzon.update', $ticket) }}" class="space-y-2">@csrf @method('PATCH')
                                        <select name="estado" class="input w-full">@foreach ($estados as $value => $stateLabel)<option value="{{ $value }}" @selected($ticket->estado === $value)>{{ $stateLabel }}</option>@endforeach</select>
                                        <select name="asignado_a" class="input w-full"><option value="">Sin asignar</option>@foreach ($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected($ticket->asignado_a === $usuario->id)>{{ $usuario->name }}</option>@endforeach</select>
                                        <textarea name="notas_internas" class="input w-full" rows="3" placeholder="Notas internas">{{ $ticket->notas_internas }}</textarea><button class="btn btn-primary btn-sm">Guardar</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.buzon.destroy', $ticket) }}" class="mt-2" onsubmit="return confirm('¿Eliminar este reporte del tablero?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Eliminar</button></form>
                                </details>
                            </article>
                        @empty <p class="bz-board-empty">Sin reportes</p> @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection
