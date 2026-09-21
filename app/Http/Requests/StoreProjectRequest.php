<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Permission;
use App\Models\Role;
use App\Support\DetailLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->is_active || $user->hasRole(Role::AUDITOR)) {
            return false;
        }

        return $user->hasPermission(Permission::PROJECTS_MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('projects', 'code')->withoutTrashed(),
            ],
            'description' => ['nullable', 'string'],
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->withoutTrashed()],
            'template_id' => ['nullable', 'integer', Rule::exists('project_templates', 'id')->withoutTrashed()],

            // Cuánto enseña el proyecto. `nullable` y no `required` para que un
            // alta por integración —o una pantalla en caché de antes de que
            // existiera el campo— caiga en Estándar en vez de ser rechazada.
            'detail_level' => ['nullable', Rule::enum(DetailLevel::class)],

            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->withoutTrashed()],

            // La fecha de inicio dejó de ser obligatoria en la pantalla, pero
            // el plan sí necesita una: `prepareForValidation` pone hoy cuando
            // viene vacía, así que aquí ya siempre llega.
            'planned_start' => ['required', 'date'],
            'planned_finish' => ['nullable', 'date', 'after_or_equal:planned_start'],

            // Paso 4 — cómo se mide. Los entregables entran como tareas de
            // primer nivel, que es lo que evita terminar el asistente en una
            // pantalla vacía.
            'success_criteria' => ['nullable', 'string', 'max:2000'],
            'deliverables' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Los entregables, uno por renglón, ya limpios.
     *
     * @return list<string>
     */
    public function deliverableList(): array
    {
        $raw = (string) $this->input('deliverables', '');

        $lines = array_map(
            fn (string $line): string => trim(ltrim(trim($line), '-•* ')),
            preg_split('/\r\n|\r|\n/', $raw) ?: [],
        );

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('initiation.project_code_help'),
        ];
    }

    /** El nivel pedido, o Estándar si no vino ninguno válido. */
    public function detailLevel(): DetailLevel
    {
        $value = $this->input('detail_level');

        return DetailLevel::fromNullable(is_string($value) ? $value : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'org_unit_id' => $this->input('org_unit_id') ?: null,
            'template_id' => $this->input('template_id') ?: null,
            'planned_finish' => $this->input('planned_finish') ?: null,

            // Sin fecha de inicio el proyecto arranca hoy. Es lo que la gente
            // quiere decir cuando deja el campo vacío, y deja de ser un error
            // que la manda de vuelta al formulario por algo que no le
            // preguntamos en la parte visible de la pantalla.
            //
            // «Hoy» en la zona de la aplicación y no en la del usuario, que es
            // solo de presentación: el motor programa con la zona del
            // calendario del proyecto, y mezclar las tres para adivinar un día
            // cuesta más de lo que vale. Quien necesite otra fecha la escribe.
            'planned_start' => $this->input('planned_start') ?: now()->toDateString(),
        ]);
    }
}
