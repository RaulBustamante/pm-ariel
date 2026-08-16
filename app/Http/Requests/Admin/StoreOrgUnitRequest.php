<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OrgUnit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('org_units', 'code')->withoutTrashed()],
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->withoutTrashed()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Un select vacío llega como cadena vacía, que no es lo mismo que "sin
        // padre" para una llave foránea.
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'code' => $this->input('code') ?: null,
            'sort_order' => $this->input('sort_order') ?: 0,
        ]);
    }
}
