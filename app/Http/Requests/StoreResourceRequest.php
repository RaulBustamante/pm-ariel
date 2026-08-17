<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Resource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un recurso.
 *
 * Los campos de costo son **anulables a propósito**. Alguien tiene que poder dar
 * de alta a las personas del equipo el lunes y conseguir las tarifas el jueves;
 * obligar a la tarifa desde el principio solo consigue que se inventen números
 * para poder guardar, y un costo inventado es peor que un costo ausente —el
 * ausente se ve en el reporte, el inventado no.
 *
 * Lo que sí se cuida es que el tipo y su unidad concuerden: un material sin
 * unidad de medida deja el reporte diciendo «300» sin decir de qué.
 */
final class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Resource::types())],
            'role_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],

            // Capacidad solo para lo que aporta horas. Un material no tiene
            // jornada que exceder, así que tampoco tiene capacidad.
            'capacity_percent' => ['nullable', 'integer', 'between:1,500'],

            'cost_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'unit_of_measure' => ['nullable', 'string', 'max:30'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'is_external' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Un material con costo y sin unidad deja el reporte diciendo «300»
            // sin decir de qué: trescientos kilos, piezas o litros son tres
            // presupuestos distintos.
            if ($this->input('type') === Resource::TYPE_MATERIAL
                && filled($this->input('cost_per_unit'))
                && blank($this->input('unit_of_measure'))) {
                $validator->errors()->add('unit_of_measure', __('resources.unit_required_with_cost'));
            }
        });
    }

    /**
     * Las casillas sin marcar no viajan en el formulario. Sin esto, apagar
     * «externo» o «activo» no los apagaría: el campo simplemente no llegaría.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_external' => $this->boolean('is_external'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
