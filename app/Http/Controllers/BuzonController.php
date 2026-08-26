<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuzonAdjunto;
use App\Models\BuzonTicket;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BuzonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $tickets = BuzonTicket::query()
            ->with(['user', 'asignadoA', 'adjuntos'])
            ->when($request->filled('tipo'), fn ($query) => $query->where('tipo', $request->string('tipo')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($nested) => $nested->where('folio', 'like', $term)
                    ->orWhere('titulo', 'like', $term)
                    ->orWhere('descripcion', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->groupBy('estado');

        return view('buzon.index', [
            'tickets' => $tickets,
            'estados' => BuzonTicket::estados(),
            'usuarios' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('buzon', [
            'tipo' => ['required', Rule::in(array_keys(BuzonTicket::tipos()))],
            'titulo' => ['required', 'string', 'min:5', 'max:180'],
            'descripcion' => ['required', 'string', 'min:10', 'max:5000'],
            'severidad' => [
                Rule::requiredIf($request->input('tipo') === BuzonTicket::TIPO_ERROR),
                'nullable',
                Rule::in(array_keys(BuzonTicket::severidades())),
            ],
            'url' => ['required', 'string', 'max:500'],
            'ruta_nombre' => ['nullable', 'string', 'max:150'],
            'contexto' => ['nullable', 'string', 'max:12000'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ], [
            'titulo.min' => 'Escribe un título de al menos 5 caracteres.',
            'descripcion.min' => 'Da un poco más de detalle, al menos 10 caracteres.',
            'severidad.required' => 'Dinos qué tanto te afecta.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.max' => 'La imagen no debe pesar más de 5 MB.',
        ]);

        $contexto = json_decode($validated['contexto'] ?? '{}', true);
        $contexto = is_array($contexto) ? $contexto : [];
        $userAgent = mb_substr((string) ($contexto['userAgent'] ?? $request->userAgent() ?? ''), 0, 1000);

        $ticket = BuzonTicket::query()->create([
            'user_id' => $request->user()->id,
            'tipo' => $validated['tipo'],
            'titulo' => trim($validated['titulo']),
            'descripcion' => trim($validated['descripcion']),
            'severidad' => $validated['tipo'] === BuzonTicket::TIPO_ERROR ? $validated['severidad'] : null,
            'url' => $validated['url'],
            'ruta_nombre' => $validated['ruta_nombre'] ?? null,
            'navegador' => $this->browser($userAgent),
            'sistema_operativo' => $this->operatingSystem($userAgent),
            'resolucion' => mb_substr((string) ($contexto['resolucion'] ?? ''), 0, 40) ?: null,
            'user_agent' => $userAgent ?: null,
            'errores_consola' => array_slice((array) ($contexto['errores'] ?? []), -5),
            'estado' => BuzonTicket::ESTADO_NUEVO,
        ]);

        $ticket->update(['folio' => sprintf('BZN-%s-%05d', now()->format('Y'), $ticket->id)]);

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $name = sprintf('%s-%s.%s', $ticket->folio, bin2hex(random_bytes(6)), $file->extension());
            $path = $file->storeAs('buzon/'.$ticket->id, $name, 'local');

            $ticket->adjuntos()->create([
                'ruta_archivo' => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'tamano' => $file->getSize(),
            ]);
        }

        return back()->with('buzon_enviado', $ticket->folio);
    }

    public function update(Request $request, BuzonTicket $ticket): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(array_keys(BuzonTicket::estados()))],
            'asignado_a' => ['nullable', 'exists:users,id'],
            'notas_internas' => ['nullable', 'string', 'max:5000'],
        ]);

        $wasResolved = $ticket->estado === BuzonTicket::ESTADO_RESUELTO;
        $isResolved = $validated['estado'] === BuzonTicket::ESTADO_RESUELTO;
        $ticket->estado = $validated['estado'];
        if (array_key_exists('asignado_a', $validated)) {
            $ticket->asignado_a = $validated['asignado_a'];
        }
        if (array_key_exists('notas_internas', $validated)) {
            $ticket->notas_internas = $validated['notas_internas'];
        }
        $ticket->resuelto_en = $isResolved ? ($ticket->resuelto_en ?? now()) : ($wasResolved ? null : $ticket->resuelto_en);
        $ticket->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'estado' => $ticket->estado]);
        }

        return back()->with('status', "{$ticket->folio} actualizado.");
    }

    public function destroy(Request $request, BuzonTicket $ticket): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $ticket->delete();

        return back()->with('status', "{$ticket->folio} se eliminó del tablero.");
    }

    public function attachment(Request $request, BuzonAdjunto $adjunto): StreamedResponse
    {
        $ticket = $adjunto->ticket;
        abort_unless($ticket && ($ticket->user_id === $request->user()->id || $this->isAdmin($request->user())), 403);
        abort_unless(Storage::disk('local')->exists($adjunto->ruta_archivo), 404);

        return Storage::disk('local')->download($adjunto->ruta_archivo, $adjunto->nombre_original);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($this->isAdmin($request->user()), 403);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    private function browser(string $agent): ?string
    {
        foreach (['Edg/' => 'Edge', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Version/' => 'Safari'] as $needle => $name) {
            if (preg_match('#'.preg_quote($needle, '#').'([\d.]+)#', $agent, $match)) {
                return $name.' '.$match[1];
            }
        }

        return $agent ? 'Otro' : null;
    }

    private function operatingSystem(string $agent): ?string
    {
        return match (true) {
            str_contains($agent, 'Windows NT 10.0') => 'Windows 10/11',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            preg_match('/iPhone|iPad/', $agent) === 1 => 'iOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => $agent ? 'Otro' : null,
        };
    }
}
