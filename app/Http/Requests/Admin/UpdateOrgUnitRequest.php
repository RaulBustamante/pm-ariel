<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->target();

        return $unit !== null && ($this->user()?->can('update', $unit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->target();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('org_units', 'code')->ignore($unit?->id)->withoutTrashed(),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->withoutTrashed()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * Colgar un área de sí misma o de una de sus descendientes desconecta a las
     * dos del árbol: dejan de tener raíz y ya no aparecen en ningún listado. La
     * base de datos no lo impide, así que se impide aquí.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unit = $this->target();
                $parentId = $this->input('parent_id');

                if ($unit === null || $parentId === null) {
                    return;
                }

                if ((int) $parentId === $unit->id) {
                    $validator->errors()->add('parent_id', __('org_units.cannot_be_its_own_parent'));

                    return;
                }

                $parent = OrgUnit::query()->find($parentId);

                if ($parent !== null && $unit->isAncestorOf($parent)) {
                    $validator->errors()->add('parent_id', __('org_units.cannot_move_under_descendant'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'code' => $this->input('code') ?: null,
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }

    private function target(): ?OrgUnit
    {
        $unit = $this->route('orgUnit');

        return $unit instanceof OrgUnit ? $unit : null;
    }
}
