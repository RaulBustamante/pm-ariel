<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
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
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'locale' => $this->input('locale', config('app.locale')),
            'timezone' => $this->input('timezone', config('app.timezone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        // Los roles se sincronizan aparte: no son columnas de `users`.
        return collect(parent::validated())->except('roles')->all();
    }
}
