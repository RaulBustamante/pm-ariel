<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProjectRequest extends FormRequest
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
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('projects', 'code')
                    ->ignore($project instanceof Project ? $project->id : null)
                    ->withoutTrashed(),
            ],
            'description' => ['nullable', 'string'],
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->withoutTrashed()],
            'planned_start' => ['nullable', 'date'],
            'planned_finish' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'active', 'on_hold', 'closed', 'cancelled'])],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'org_unit_id' => $this->input('org_unit_id') ?: null,
            'planned_start' => $this->input('planned_start') ?: null,
            'planned_finish' => $this->input('planned_finish') ?: null,
            'currency' => strtoupper((string) $this->input('currency', 'MXN')),
        ]);
    }
}
