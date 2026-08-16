<?php

declare(strict_types=1);

namespace App\Http\Requests\Initiation;

use App\Models\Project;
use App\Models\RiskResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRiskResponseRequest extends FormRequest
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
            'strategy' => ['required', Rule::in(RiskResponse::STRATEGIES)],
            'description' => ['required', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(RiskResponse::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_id' => $this->input('owner_id') ?: null,
            'due_date' => $this->input('due_date') ?: null,
            'status' => $this->input('status') ?: RiskResponse::STATUS_PENDING,
        ]);
    }
}
