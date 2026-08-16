<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Preferencias propias. No hay autorización que evaluar más allá de estar
 * dentro: nadie edita las de otro por aquí, el usuario sale de la sesión.
 */
final class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['es']);

        return [
            'locale' => ['required', 'string', Rule::in($supported)],
            'timezone' => ['required', 'string', 'timezone'],
            'expert_mode' => ['boolean'],
        ];
    }

    /**
     * Una casilla sin marcar no se envía. Sin esto, apagar el Modo Experto
     * no lo apagaría: el campo simplemente no llegaría.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['expert_mode' => $this->boolean('expert_mode')]);
    }
}
