<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Algo que el sistema detectó y que amenaza la entrega.
 *
 * **Avisa y explica; no propone la jugada** (D-017). El campo
 * `suggested_action` existe y está vacío a propósito: detectar que un recurso
 * está al 180 % es aritmética que se puede auditar; decir "muévelo al martes"
 * es un juicio que hoy no tiene con qué respaldarse, porque no hay historial de
 * proyectos de Ariel del cual aprender qué funcionó.
 *
 * Las dos referencias son opcionales y por eso van anotadas: un aviso de recurso
 * sobreasignado no apunta a ninguna tarea, y uno de tarea crítica sin
 * responsable no apunta a ningún recurso. Las dos llaves son anulables en la
 * migración, pero el genérico de `BelongsTo` no lo expresa y el análisis
 * estático las daría por presentes.
 *
 * El de recurso va con la ruta completa porque `resource` es un tipo reservado
 * de PHP y Pint lo pasa a minúsculas si se escribe suelto, con lo que deja de
 * apuntar al modelo.
 *
 * @property-read Task|null $task
 * @property-read \App\Models\Resource|null $resource
 */
#[Fillable([
    'project_id', 'rule', 'severity', 'message', 'why',
    'task_id', 'resource_id', 'suggested_action', 'detected_at',
])]
class ProjectFinding extends Model
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /** @var list<string> De más grave a menos, para ordenar sin inventar reglas. */
    public const SEVERITY_ORDER = [self::SEVERITY_CRITICAL, self::SEVERITY_WARNING, self::SEVERITY_INFO];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['detected_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * `resource` es un tipo reservado de PHP, así que el nombre corto se
     * convierte a minúsculas al dar formato y deja de referirse al modelo. El
     * nombre completo evita la ambigüedad.
     *
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }
}
