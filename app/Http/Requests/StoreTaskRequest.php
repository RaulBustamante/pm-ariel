<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\DurationParser;
use App\Support\Scheduling\ProjectDurations;
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
            'duration' => ['nullable', 'string', 'max:20'],
            'constraint_type' => ['nullable', Rule::in(array_column(ConstraintType::cases(), 'value'))],
            'constraint_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'cost' => ['nullable', 'numeric', 'min:0'],

            // Nulo y cero **no** son lo mismo: nulo es <<no se ha
            // capturado>> y cero es <<salio gratis>>. De esa diferencia
            // depende que el valor ganado sepa si puede calcular el CPI.
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'percent_complete' => ['nullable', 'numeric', 'between:0,100'],
            'predecessors' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
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
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_id' => $this->input('owner_id') ?: null,
            'parent_id' => $this->input('parent_id') ?: null,
            'constraint_type' => $this->input('constraint_type') ?: ConstraintType::AsSoonAsPossible->value,
            'constraint_date' => $this->input('constraint_date') ?: null,
        ]);
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
