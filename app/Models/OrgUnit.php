<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'name', 'code', 'sort_order'])]
class OrgUnit extends Model
{
    use RecordsAudit, SoftDeletes;

    protected function casts(): array
    {
        return ['depth' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // La ruta y la profundidad son derivadas: el usuario nunca las escribe,
        // igual que el WBS code de una tarea.
        static::saving(function (self $unit): void {
            $parent = $unit->parent_id ? self::find($unit->parent_id) : null;

            $unit->depth = $parent ? $parent->depth + 1 : 0;
            $unit->path = $parent ? $parent->path.$unit->parent_id.'/' : '/';
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
