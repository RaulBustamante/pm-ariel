<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Documents\AcceptanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Levantar o corregir un acta de aceptación.
 *
 * **Quien acepta va como texto libre y es obligatorio.** Casi siempre es alguien
 * de fuera del equipo —un cliente, otra área, un auditor— que no tiene cuenta en
 * el sistema; exigir un usuario obligaría a crear cuentas falsas para poder
 * cerrar un acta. Pero sin nombre el acta no dice nada: «se aceptó» sin decir
 * quién es justo el documento que no sirve el día que alguien lo discute.
 */
final class StoreAcceptanceRecordRequest extends FormRequest
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
        $project = $this->route('project');

        return [
            'subject' => ['required', 'string', 'max:200'],
            'detail' => ['nullable', 'string', 'max:20000'],

            // El entregable tiene que ser una tarea **de este proyecto**: sin
            // acotarlo, un identificador escrito a mano aceptaría el entregable
            // de otro cliente.
            'task_id' => [
                'nullable', 'integer',
                Rule::exists('tasks', 'id')
                    ->where('project_id', $project instanceof Project ? $project->id : 0)
                    ->withoutTrashed(),
            ],

            'decision' => ['required', Rule::in(app(AcceptanceRecord::class)->decisions())],
            'reservations' => ['nullable', 'string', 'max:20000'],
            'accepted_by_name' => ['required', 'string', 'max:255'],
            'accepted_by_role' => ['nullable', 'string', 'max:255'],
            'accepted_by_org' => ['nullable', 'string', 'max:255'],
            'accepted_on' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Aceptar con reservas sin decir cuáles deja el acta afirmando que
            // hay condiciones y sin decir ninguna — que es la forma más rápida
            // de que se discutan tres meses después.
            if (in_array($this->input('decision'), ['accepted_with_reservations', 'rejected'], true)
                && blank($this->input('reservations'))) {
                $validator->errors()->add('reservations', __('records.reservations_required'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['task_id' => $this->input('task_id') ?: null]);
    }
}
