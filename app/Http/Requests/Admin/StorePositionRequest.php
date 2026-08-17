<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePositionRequest extends FormRequest
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
        $position = $this->route('position');

        return [
            'name' => [
                'required', 'string', 'max:120',
                // Dos puestos con el mismo nombre solo consiguen que quien da de
                // alta a alguien tenga que adivinar cuál de los dos es.
                Rule::unique('positions', 'name')
                    ->ignore($position instanceof Position ? $position->id : null)
                    ->withoutTrashed(),
            ],
            // 1 es lo más alto de la organización. Se acota a 20 porque una
            // jerarquía de más de veinte escalones no es una jerarquía, es un
            // error de captura.
            'level' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
