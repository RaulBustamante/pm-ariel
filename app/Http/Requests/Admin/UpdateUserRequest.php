<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return $target !== null && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target->id)->withoutTrashed(),
            ],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'timezone' => ['required', 'timezone'],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->withoutTrashed()],
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],

            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        return collect(parent::validated())->except('roles')->all();
    }
}
