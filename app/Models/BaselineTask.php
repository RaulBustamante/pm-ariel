<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La foto de una tarea en el momento de capturar la línea base.
 *
 * No tiene llave foránea contra `tasks` a propósito: si la tarea se borra del
 * plan, la línea base debe seguir diciendo qué se había comprometido. Una línea
 * base que cambia cuando cambia el plan no es una línea base.
 */
#[Fillable(['baseline_id', 'task_id', 'wbs_code', 'name', 'start', 'finish', 'duration_minutes', 'cost'])]
class BaselineTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'finish' => 'datetime',
            'duration_minutes' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Baseline, $this>
     */
    public function baseline(): BelongsTo
    {
        return $this->belongsTo(Baseline::class);
    }
}
