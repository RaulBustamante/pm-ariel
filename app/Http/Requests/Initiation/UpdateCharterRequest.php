<?php

declare(strict_types=1);

namespace App\Http\Requests\Initiation;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida un paso del recorrido. Cada paso manda solo sus campos, y ninguno es
 * obligatorio aquí: el recorrido se puede abandonar a medias y retomarse.
 *
 * Lo que falta no se impide, se señala — de eso se encarga `InitiationHealth`.
 * Obligar a llenar todo antes de poder guardar es la forma más segura de que la
 * gente invente texto para poder avanzar.
 */
final class UpdateCharterRequest extends FormRequest
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
            'problem_statement' => ['nullable', 'string', 'max:5000'],
            'opportunity' => ['nullable', 'string', 'max:5000'],
            'expected_benefit' => ['nullable', 'string', 'max:5000'],
            'alignment' => ['nullable', 'string', 'max:5000'],

            'objectives' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['nullable', 'string', 'max:5000'],
            'success_criteria' => ['nullable', 'string', 'max:5000'],
            'assumptions' => ['nullable', 'string', 'max:5000'],
            'constraints' => ['nullable', 'string', 'max:5000'],
            'out_of_scope' => ['nullable', 'string', 'max:5000'],
            'high_level_milestones' => ['nullable', 'string', 'max:5000'],

            'sponsor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sponsor_id')) {
            $this->merge(['sponsor_id' => $this->input('sponsor_id') ?: null]);
        }
    }
}
