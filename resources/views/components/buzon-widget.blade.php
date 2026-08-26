@php
    $misReportes = \App\Models\BuzonTicket::query()
        ->where('user_id', auth()->id())->latest()->limit(10)->get();
@endphp

<div class="bz-root" data-buzon-root data-open-on-load="{{ $errors->buzon->any() ? 'true' : 'false' }}">
    <button type="button" class="bz-fab" data-buzon-open aria-label="Enviar un comentario sobre la plataforma">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Buzón</span>
    </button>

    <div class="bz-overlay" data-buzon-close hidden></div>
    <section class="bz-panel" role="dialog" aria-modal="true" aria-labelledby="bz-title" hidden>
        <header class="bz-header">
            <div><p>{{ config('branding.name') }}</p><h2 id="bz-title">Enviar comentario</h2></div>
            <button type="button" class="bz-close" data-buzon-close aria-label="Cerrar">×</button>
        </header>

        @if (session('buzon_enviado'))
            <div class="bz-done" data-buzon-success>
                <span class="bz-done-icon">✓</span>
                <strong>Listo. Gracias por reportarlo.</strong>
                <p>Guardamos tu comentario como {{ session('buzon_enviado') }}.</p>
            </div>
        @endif

        <div class="bz-step" data-buzon-step="choose" @if ($errors->buzon->any() || session('buzon_enviado')) hidden @endif>
            <div class="bz-body">
                <p class="bz-prompt">¿Qué nos quieres contar?</p>
                <button type="button" class="bz-choice" data-buzon-type="error">
                    <span class="bz-choice-icon bz-choice-error">!</span>
                    <span><strong>Reportar un error</strong><small>Algo no está funcionando</small></span>
                </button>
                <button type="button" class="bz-choice" data-buzon-type="sugerencia">
                    <span class="bz-choice-icon bz-choice-idea">✦</span>
                    <span><strong>Enviar una sugerencia</strong><small>Una idea para mejorar</small></span>
                </button>
                @if ($misReportes->isNotEmpty())
                    <button type="button" class="bz-history-link" data-buzon-history>Ver mis reportes ({{ $misReportes->count() }})</button>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('buzon.store') }}" enctype="multipart/form-data" class="bz-step" data-buzon-step="form" @if (! $errors->buzon->any()) hidden @endif>
            @csrf
            <input type="hidden" name="tipo" value="{{ old('tipo') }}" data-buzon-tipo>
            <input type="hidden" name="ruta_nombre" value="{{ request()->route()?->getName() }}">
            <input type="hidden" name="contexto" value="" data-buzon-context>
            <div class="bz-body">
                <button type="button" class="bz-back" data-buzon-back>← Cambiar tipo</button>

                <label class="bz-label" for="bz-titulo">Título</label>
                <input id="bz-titulo" name="titulo" class="bz-input" maxlength="180" value="{{ old('titulo') }}" required>
                @error('titulo', 'buzon') <span class="bz-error">{{ $message }}</span> @enderror

                <fieldset class="bz-severity" data-buzon-severity>
                    <legend class="bz-label">¿Qué tanto te afecta?</legend>
                    <div class="bz-chips">
                        @foreach (\App\Models\BuzonTicket::severidades() as $value => $label)
                            <label class="bz-chip"><input type="radio" name="severidad" value="{{ $value }}" @checked(old('severidad') === $value)> {{ $label }}</label>
                        @endforeach
                    </div>
                    @error('severidad', 'buzon') <span class="bz-error">{{ $message }}</span> @enderror
                </fieldset>

                <label class="bz-label" for="bz-descripcion">Descripción</label>
                <textarea id="bz-descripcion" name="descripcion" class="bz-textarea" rows="4" required>{{ old('descripcion') }}</textarea>
                @error('descripcion', 'buzon') <span class="bz-error">{{ $message }}</span> @enderror

                <label class="bz-label" for="bz-url">Liga de la pantalla</label>
                <input id="bz-url" name="url" class="bz-input" maxlength="500" value="{{ old('url', url()->current()) }}" required>
                <small class="bz-help">Si ocurrió en otra pantalla, pega aquí esa liga.</small>

                <label class="bz-upload" for="bz-imagen">
                    <span>Adjuntar una imagen <small>Opcional · máximo 5 MB</small></span>
                    <input id="bz-imagen" type="file" name="imagen" accept="image/*" data-buzon-image>
                </label>
                <img class="bz-preview" alt="Vista previa de la imagen que vas a enviar" data-buzon-preview hidden>
                @error('imagen', 'buzon') <span class="bz-error">{{ $message }}</span> @enderror

                <div class="bz-context-copy">
                    <strong>Se envía automáticamente</strong>
                    <span>Navegador, sistema, resolución y hasta 5 errores de esta sesión.</span>
                    <small>No se toma ninguna captura de tu pantalla.</small>
                </div>
            </div>
            <footer class="bz-footer">
                <button type="button" class="btn btn-ghost btn-sm" data-buzon-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
            </footer>
        </form>

        <div class="bz-step" data-buzon-step="history" hidden>
            <div class="bz-body">
                <button type="button" class="bz-back" data-buzon-back>← Volver</button>
                <div class="bz-history">
                    @foreach ($misReportes as $reporte)
                        <article>
                            <div><span>{{ $reporte->folio }}</span><em class="bz-state bz-state-{{ $reporte->estado }}">{{ $reporte->estado_label }}</em></div>
                            <strong>{{ $reporte->titulo }}</strong>
                            <small>Enviado {{ $reporte->created_at->diffForHumans() }}</small>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
