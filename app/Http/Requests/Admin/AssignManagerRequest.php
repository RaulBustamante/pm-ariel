<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->withoutTrashed(),
                // Nadie es su propio jefe. El servicio también lo rechaza; aquí
                // es donde el usuario recibe el mensaje en su idioma.
                Rule::notIn([$target instanceof User ? $target->id : 0]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.not_in' => __('hierarchy.cannot_manage_self'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['manager_id' => $this->input('manager_id') ?: null]);
    }
}
