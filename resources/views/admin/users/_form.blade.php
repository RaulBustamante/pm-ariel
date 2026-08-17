@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
    $selectedRoles = old('roles', $user?->roles->pluck('id')->all() ?? []);
@endphp

<div class="space-y-4">
    <x-form-field name="name" :label="__('common.name')" :value="$user->name ?? ''" required autofocus />
    <x-form-field name="email" :label="__('common.email')" type="email" :value="$user->email ?? ''" required />

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="locale-field" class="block text-sm font-medium text-slate-700">{{ __('common.language') }}</label>
            <select id="locale-field" name="locale"
                    class="field">
                @foreach (config('app.supported_locales') as $locale)
                    <option value="{{ $locale }}" @selected(old('locale', $user->locale ?? config('app.locale')) === $locale)>
                        {{ strtoupper($locale) }}
                    </option>
                @endforeach
            </select>
            @error('locale') <p role="alert" class="text-sm text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
        </div>

        <x-form-field name="timezone" :label="__('common.timezone')"
                      :value="$user->timezone ?? config('app.timezone')" required />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="org-unit-field" class="block text-sm font-medium text-slate-700">{{ __('common.org_unit') }}</label>
            <select id="org-unit-field" name="org_unit_id"
                    class="field">
                <option value="">—</option>
                @foreach ($orgUnits as $unit)
                    <option value="{{ $unit->id }}" @selected((int) old('org_unit_id', $user->org_unit_id ?? 0) === $unit->id)>
                        {{ $unit->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="position-field" class="block text-sm font-medium text-slate-700">{{ __('common.position') }}</label>
            <select id="position-field" name="position_id"
                    class="field">
                <option value="">—</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected((int) old('position_id', $user->position_id ?? 0) === $position->id)>
                        {{ $position->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    @can('assignRoles', App\Models\User::class)
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-slate-700">{{ __('users.roles') }}</legend>
            <p class="text-xs text-slate-500">{{ __('users.roles_help') }}</p>

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                               @checked(in_array($role->id, array_map('intval', (array) $selectedRoles), true))
                               class="rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-hud-500">
                        {{ $role->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endcan

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $user->is_active ?? true))
               class="rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-hud-500">
        {{ __('common.active') }}
    </label>
</div>
