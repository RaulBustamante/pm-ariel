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

    /**
     * Tope de profundidad al recalcular un subárbol. La estructura real tiene
     * pocos niveles; el número existe para que un ciclo que se colara en los
     * datos no se vuelva un bucle infinito silencioso, igual que en
     * VisibilityScope.
     */
    private const MAX_DEPTH = 20;

    /**
     * @return array<string, string>
     */
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

        // Mover un área mueve todo lo que cuelga de ella. Sin esto, las hijas
        // conservan la ruta anterior y un subárbol consultado por `path` empieza
        // a devolver mentiras sin que nada falle.
        static::updated(function (self $unit): void {
            if ($unit->wasChanged('path')) {
                $unit->refreshDescendants();
            }
        });
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * ¿Este candidato está debajo de esta área? Colgar un área de su propia
     * descendiente desconectaría a las dos del árbol sin que nada reclamara.
     */
    public function isAncestorOf(self $candidate): bool
    {
        return str_contains((string) $candidate->path, "/{$this->id}/");
    }

    private function refreshDescendants(): void
    {
        $frontier = [$this->id];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $children = self::query()->whereIn('parent_id', $frontier)->get();

            // Guardar recalcula ruta y profundidad desde el padre ya corregido.
            $children->each->save();

            $frontier = $children->pluck('id')->all();
        }
    }
}
