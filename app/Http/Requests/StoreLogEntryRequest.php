<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Documents\ProjectLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un renglón de cualquiera de los catorce registros.
 *
 * Un solo formulario, porque son un solo tipo de cosa. Lo único que cambia entre
 * una incidencia y una lección es el juego de estados, y eso se resuelve
 * preguntándole al motor en tiempo de validación en vez de escribir catorce
 * reglas.
 *
 * **Se pide poco: la fecha, una línea de qué pasó y el estado.** El detalle, el
 * dueño y el desenlace son anulables a propósito. Un registro que exige llenar
 * ocho campos para anotar una incidencia a media junta no se llena: se anota en
 * una libreta y se pierde, que es exactamente lo que este bloque existe para
 * evitar.
 */
final class StoreLogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $code = (string) $this->route('code');
        $log = app(ProjectLog::class);

        return [
            'occurred_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:200'],
            'detail' => ['nullable', 'string', 'max:20000'],

            // El estado se valida contra el juego **de este registro**: un
            // «rechazado» en una minuta pintaría un renglón sin distintivo y sin
            // que nada falle.
            'status' => ['required', Rule::in($log->statuses($code))],

            // El dueño tiene que ser alguien que exista y siga activo. Asignarle
            // un pendiente a una cuenta dada de baja es dejarlo sin dueño sin
            // que se note.
            'owner_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true)->withoutTrashed(),
            ],

            'due_on' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in((array) config('pmi_logs.priorities', []))],
            'outcome' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Los campos que no aplican a este registro no viajan en el formulario, y un
     * campo ausente no se distingue de uno vaciado. El motor se queda solo con
     * lo que pertenece al tipo, así que aquí basta con no estorbar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_id' => $this->input('owner_id') ?: null,
            'due_on' => $this->input('due_on') ?: null,
            'priority' => $this->input('priority') ?: null,
        ]);
    }
}
