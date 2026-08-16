<?php

declare(strict_types=1);

namespace App\Http\Requests\Initiation;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStakeholderRequest extends FormRequest
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
            'organization' => ['nullable', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],

            // 1 a 5. Fuera de rango el cuadrante deja de significar algo.
            'power' => ['required', 'integer', 'between:1,5'],
            'interest' => ['required', 'integer', 'between:1,5'],

            'expectations' => ['nullable', 'string', 'max:2000'],
            'engagement_strategy' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['user_id' => $this->input('user_id') ?: null]);
    }
}
