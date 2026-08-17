<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['task_id', 'resource_id', 'units_percent', 'quantity'])]
class TaskAssignment extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // `quantity` con tres decimales: hay materiales que se piden en
        // fracciones —1.5 litros, 0.75 metros— y redondear ahí falsea el costo.
        return ['units_percent' => 'integer', 'quantity' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Nombre completo a propósito: `resource` es un tipo reservado de PHP y el
     * nombre corto se convierte a minúsculas al dar formato.
     *
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
