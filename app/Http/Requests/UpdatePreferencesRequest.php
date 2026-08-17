<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
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
            // `sometimes` y no `required`: quien mande el formulario sin el
            // tema —una pantalla vieja en caché, una petición de una integración
            // futura— conserva el suyo en vez de recibir un error por un campo
            // que no sabía que existía. Lo que sí llega tiene que ser uno de los
            // tres; un valor inventado se rechaza.
            'theme' => ['sometimes', 'required', 'string', Rule::in(User::themes())],
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
