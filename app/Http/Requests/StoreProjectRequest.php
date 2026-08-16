<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Permission;
use App\Models\Role;
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
        ];
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'org_unit_id' => $this->input('org_unit_id') ?: null,
            'template_id' => $this->input('template_id') ?: null,
        ]);
    }
}
