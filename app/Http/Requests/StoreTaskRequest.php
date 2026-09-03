<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\DurationParser;
use App\Support\Scheduling\ProjectDurations;
use App\Support\Tasks\WaitingReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Una tarea, capturada como la escribe la gente: "3d" y "12FS+2d", no minutos
 * ni identificadores internos. La traducción ocurre aquí, antes de que nada
 * llegue al motor.
 */
final class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Las notas de la tarea. La columna existe desde la Etapa 3 y
            // ninguna pantalla la escribia: una columna que nadie llena es
            // lo mismo que una que no existe, y peor, porque parece que si.
            'description' => ['nullable', 'string', 'max:20000'],
            'duration' => ['nullable', 'string', 'max:20'],
            'constraint_type' => ['nullable', Rule::in(array_column(ConstraintType::cases(), 'value'))],
            'constraint_date' => ['nullable', 'date'],
            'requested_start' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],

            // Acotado al proyecto: sin eso, un identificador escrito a mano
            // programaria esta tarea con la jornada de otro cliente.
            'calendar_id' => [
                'nullable', 'integer',
                // Explicito y no `?->`: se distingue <<no hay proyecto en la
                // ruta>> --que no deberia pasar-- de <<el proyecto no tiene
                // id>>, y el analisis estatico tipa la ruta como no nula.
                Rule::exists('calendars', 'id')->where(
                    'project_id',
                    $this->route('project') instanceof Project ? $this->route('project')->id : 0,
                ),
            ],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'cost' => ['nullable', 'numeric', 'min:0'],

            // Nulo y cero **no** son lo mismo: nulo es <<no se ha
            // capturado>> y cero es <<salio gratis>>. De esa diferencia
            // depende que el valor ganado sepa si puede calcular el CPI.
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'percent_complete' => ['nullable', 'numeric', 'between:0,100'],

            // La espera. `waiting_since` no se valida porque no se captura: la
            // escribe el modelo al empezar la espera.
            'waiting_on' => ['nullable', Rule::in(WaitingReason::values())],
            'waiting_note' => ['nullable', 'string', 'max:255'],
            'predecessors' => ['nullable', 'string', 'max:255'],

            // El paquete dentro del que nace la tarea, acotado al proyecto por
            // la misma razon que el calendario. Hasta ahora ninguna pantalla lo
            // enviaba y la regla suelta no hacia daño; desde que el renglon
            // tiene su «+», un numero escrito a mano en la direccion colgaria
            // la tarea del arbol de otro cliente — y ahi no volveria a
            // aparecer, porque `outline()` solo recorre lo de este proyecto.
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('tasks', 'id')->where(
                    'project_id',
                    $this->route('project') instanceof Project ? $this->route('project')->id : 0,
                ),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // La duración se valida traduciéndola: si el traductor la
                // entiende, es válida; si no, su mensaje ya explica por qué.
                $duration = (string) $this->input('duration', '');

                if ($duration !== '') {
                    try {
                        $this->durations()->toMinutes($duration);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('duration', $exception->getMessage());
                    }
                }

                $type = $this->input('constraint_type');

                if ($type !== null && $type !== '' && ConstraintType::from($type)->needsDate() && blank($this->input('constraint_date'))) {
                    $validator->errors()->add('constraint_date', __('tasks.constraint_needs_date'));
                }

                if ($this->filled('requested_start') && $this->filled('deadline')) {
                    $start = strtotime((string) $this->input('requested_start'));
                    $deadline = strtotime((string) $this->input('deadline'));

                    if ($start !== false && $deadline !== false && $deadline < $start) {
                        $validator->errors()->add('deadline', __('tasks.deadline_before_start'));
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'owner_id' => $this->input('owner_id') ?: null,
            'parent_id' => $this->input('parent_id') ?: null,
            'constraint_type' => $this->input('constraint_type') ?: ConstraintType::AsSoonAsPossible->value,
            'constraint_date' => $this->input('constraint_date') ?: null,
        ];

        if ($this->exists('requested_start')) {
            $normalized['requested_start'] = $this->input('requested_start') ?: null;
        }

        if ($this->exists('deadline')) {
            $normalized['deadline'] = $this->input('deadline') ?: null;
        }

        // El «—» del menú llega como cadena vacía y significa «no está
        // esperando». En la columna eso es null: un '' guardado haría que
        // `isWaiting()` dijera que sí y pintara un distintivo sin texto.
        if ($this->exists('waiting_on')) {
            $normalized['waiting_on'] = $this->input('waiting_on') ?: null;
        }

        if ($this->exists('waiting_note')) {
            $normalized['waiting_note'] = trim((string) $this->input('waiting_note')) ?: null;
        }

        $this->merge($normalized);
    }

    public function durationMinutes(): int
    {
        $duration = (string) $this->input('duration', '');

        return $duration === '' ? 0 : $this->durations()->toMinutes($duration);
    }

    /**
     * La jornada de este proyecto. Sin proyecto en la ruta —que no debería
     * pasar, porque `authorize()` ya lo exige— se usa la jornada por omisión.
     */
    private function durations(): DurationParser
    {
        $project = $this->route('project');

        return $project instanceof Project ? ProjectDurations::for($project) : new DurationParser;
    }
}
