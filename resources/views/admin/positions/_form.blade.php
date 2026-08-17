<div class="grid gap-4 sm:grid-cols-2">
    <x-form-field name="name"
                  :label="__('positions.name')"
                  :value="$position?->name"
                  :help="__('positions.name_help')"
                  required />

    <x-form-field name="level"
                  type="number"
                  :label="__('positions.level')"
                  :value="$position?->level ?? 5"
                  :help="__('positions.level_help')"
                  required />
</div>

<div class="mt-5 flex items-center gap-3">
    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
    <a href="{{ route('admin.positions.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
</div>
