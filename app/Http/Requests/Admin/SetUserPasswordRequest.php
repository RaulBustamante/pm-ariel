<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class SetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return $target !== null && ($this->user()?->can('resetPassword', $target) ?? false);
    }

    /**
     * Las mismas reglas que cuando la persona la elige sola: una contraseña
     * puesta por el administrador no puede ser más débil que una propia.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
            'must_change_password' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['must_change_password' => $this->boolean('must_change_password')]);
    }
}
