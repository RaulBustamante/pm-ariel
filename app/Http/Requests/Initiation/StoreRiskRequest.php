<?php

declare(strict_types=1);

namespace App\Http\Requests\Initiation;

use App\Models\Project;
use App\Models\Risk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:2000'],
            'cause' => ['nullable', 'string', 'max:2000'],
            'effect' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:50'],

            'probability' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],

            'kind' => ['required', Rule::in([Risk::KIND_THREAT, Risk::KIND_OPPORTUNITY])],
            'status' => ['nullable', Rule::in(Risk::STATUSES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_id' => $this->input('owner_id') ?: null,
            'status' => $this->input('status') ?: Risk::STATUS_IDENTIFIED,
        ]);
    }
}
