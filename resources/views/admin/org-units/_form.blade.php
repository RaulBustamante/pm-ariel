@php
    /** @var \App\Models\OrgUnit|null $unit */
    $unit = $unit ?? null;
@endphp

<div class="space-y-4">
    <x-form-field name="name" :label="__('org_units.name')" :value="$unit->name ?? ''" required autofocus />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field name="code" :label="__('org_units.code')" :value="$unit->code ?? ''"
                      :help="__('org_units.code_help')" />

        <x-form-field name="sort_order" type="number" :label="__('org_units.sort_order')"
                      :value="(string) ($unit->sort_order ?? 0)" :help="__('org_units.sort_order_help')" />
    </div>

    <div class="space-y-1">
        <label for="parent-field" class="block text-sm font-medium text-slate-700">
            {{ __('org_units.parent') }}
        </label>
        <select id="parent-field" name="parent_id"
                @if ($errors->has('parent_id')) aria-invalid="true" aria-describedby="parent-field-error" @endif
                class="field">
            <option value="">{{ __('org_units.no_parent') }}</option>
            @foreach ($parents as $candidate)
                <option value="{{ $candidate->id }}"
                        @selected((int) old('parent_id', $unit->parent_id ?? 0) === $candidate->id)>
                    {{ str_repeat('— ', $candidate->depth) }}{{ $candidate->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id')
            <p id="parent-field-error" role="alert" class="text-sm text-[var(--color-badge-danger-fg)]">{{ $message }}</p>
        @enderror
    </div>
</div>
