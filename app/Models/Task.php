<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\TaskConstraint;
use App\Support\Scheduling\TaskNode;
use App\Support\Tasks\WaitingReason;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una tarea guardada.
 *
 * Las columnas se dividen en dos grupos que **no se mezclan**: lo que capturó el
 * usuario (duración, restricción, costo, avance) y lo que produjo el último
 * cálculo (`early_*`, `late_*`, holguras, crítica). El motor solo escribe el
 * segundo grupo. Si algún día una pantalla escribe una fecha calculada a mano,
 * el siguiente cálculo la va a pisar — y con razón.
 *
 * Las columnas de fecha llevan cast, pero el análisis estático las lee como
 * texto si no se declaran aquí.
 *
 * @property Carbon|null $constraint_date
 * @property Carbon|null $requested_start
 * @property Carbon|null $deadline
 * @property Carbon|null $early_start
 * @property Carbon|null $early_finish
 * @property Carbon|null $late_start
 * @property Carbon|null $late_finish
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_finish
 * @property Carbon|null $waiting_since
 */
#[Fillable([
    'project_id', 'parent_id', 'name', 'description', 'duration_minutes',
    'constraint_type', 'constraint_date', 'requested_start', 'deadline', 'calendar_id', 'sort_order',
    'cost', 'actual_cost', 'percent_complete', 'actual_start', 'actual_finish', 'owner_id',
    // La espera: el tipo y la nota los captura la gente. `waiting_since`
    // **no** va aqui a proposito -- la lleva el modelo, como `actual_start`.
    'waiting_on', 'waiting_note',
])]
class Task extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'constraint_date' => 'datetime',
            'requested_start' => 'datetime',
            'deadline' => 'datetime',
            'early_start' => 'datetime',
            'early_finish' => 'datetime',
            'late_start' => 'datetime',
            'late_finish' => 'datetime',
            'total_float_minutes' => 'integer',
            'free_float_minutes' => 'integer',
            'is_critical' => 'boolean',
            'is_summary' => 'boolean',
            'cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'percent_complete' => 'decimal:2',
            'actual_start' => 'datetime',
            'actual_finish' => 'datetime',
            'waiting_since' => 'datetime',
        ];
    }

    /**
     * Las fechas reales se anotan solas al capturar avance.
     *
     * Las columnas existían desde la Etapa 3 y **nadie las escribía nunca**.
     * Eso no rompía ninguna pantalla, porque ninguna las leía; se notó al
     * intentar armar el reporte semanal, que necesita saber qué se cerró de
     * verdad esta semana y no qué estaba planeado cerrarse.
     *
     * Va en el modelo y no en los tres controladores que capturan avance —la
     * lista, el tablero, el detalle— porque el cuarto que se agregue se
     * olvidaría, y el hueco volvería a pasar inadvertido por la misma razón.
     *
     * El motor de programación no lo dispara: escribe con `query()->update()`,
     * que no pasa por los eventos del modelo. Y hace bien, porque solo toca
     * columnas calculadas.
     */
    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! $task->isDirty('percent_complete')) {
                return;
            }

            $progress = (float) $task->percent_complete;

            if ($progress > 0 && $task->actual_start === null) {
                $task->actual_start = now();
            }

            if ($progress >= 100 && $task->actual_finish === null) {
                $task->actual_finish = now();
            }

            // Reabrir una tarea borra su fecha de cierre. Dejarla puesta haría
            // que el reporte de la semana que entra siguiera presumiendo como
            // terminado algo que se volvió a abrir.
            if ($progress < 100) {
                $task->actual_finish = null;
            }

            if ($progress <= 0) {
                $task->actual_start = null;
            }

            // Una tarea terminada no espera a nadie. Si se cierra con una espera
            // puesta, la espera se va con ella: dejarla haria que el Asesor
            // siguiera reclamando seguimiento de algo ya entregado, y que el
            // distintivo dijera <<esperando aprobacion>> sobre algo aprobado.
            //
            // La nota se borra tambien. Guardarla dejaria <<falta que Sistemas
            // lo de de alta>> colgado de una tarea al 100 %, que es peor que no
            // tenerla; la bitacora de auditoria conserva la historia.
            if ($progress >= 100) {
                $task->waiting_on = null;
                $task->waiting_note = null;
            }
        });

        /*
        | El reloj de la espera.
        |
        | Va en el modelo por la misma razon que las fechas reales: lo escriben
        | el detalle, el filtro y manana lo que se agregue, y el que se agregue
        | se olvidaria de reiniciarlo.
        |
        | **Cambiar el tipo de espera reinicia la fecha** --de UAT a aprobacion
        | es una espera nueva, y arrastrar la fecha vieja diria que llevas tres
        | semanas esperando una firma que pediste hoy--. **Editar solo la nota no
        | la reinicia**: sigues esperando lo mismo, nada mas aclaraste a quien.
        */
        static::saving(function (self $task): void {
            if (! $task->isDirty('waiting_on')) {
                return;
            }

            $task->waiting_since = $task->waiting_on === null ? null : now();
        });
    }

    /**
     * Lo que se asignó a esta tarea: quién trabaja y qué se consume.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function predecessorLinks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_id');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function successorLinks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    public function isMilestone(): bool
    {
        return $this->duration_minutes === 0 && ! $this->is_summary;
    }

    /**
     * En qué estado está la tarea: `todo`, `doing` o `done`.
     *
     * **Se deriva del avance; no es una columna.** Es la decisión CL-004 llevada
     * hasta el final: un estado guardado aparte puede decir «terminada» sobre
     * una tarea al 40 %, y en cuanto el tablero y la lista discrepan una vez, la
     * gente deja de creerle a los dos.
     *
     * Vive aquí y no en cada pantalla para que la lista, el tablero y el detalle
     * no puedan clasificar distinto la misma tarea — que es exactamente lo que
     * pasaba cuando el tablero tenía su propio `group()` y la lista no tenía
     * nada.
     */
    public function state(): string
    {
        $progress = (float) $this->percent_complete;

        if ($progress >= 100) {
            return 'done';
        }

        return $progress > 0 ? 'doing' : 'todo';
    }

    /**
     * ¿Está detenida esperando algo de afuera?
     *
     * Convive con `state()` y no lo reemplaza: una tarea puede estar «en curso
     * al 85 %» **y** esperando aprobación al mismo tiempo. Son dos preguntas
     * distintas —cuánto se hizo, y por qué no avanza— y las dos tienen
     * respuesta.
     */
    public function isWaiting(): bool
    {
        return $this->waitingReason() !== null;
    }

    /**
     * El tipo de espera, o null.
     *
     * Se resuelve por el enum y no se devuelve el texto crudo de la columna: un
     * valor viejo que ya no esté en el catálogo se lee como «no está esperando»
     * en vez de pintar un distintivo sin traducción.
     */
    public function waitingReason(): ?WaitingReason
    {
        return WaitingReason::tryFrom((string) $this->waiting_on);
    }

    /**
     * Días corridos que lleva esperando.
     *
     * Corridos y no laborales, a propósito: es el número que se lee al lado de
     * una fecha, y quien está esperando una respuesta no deja de esperarla el
     * sábado. El umbral del Asesor sí cuenta días laborales, porque ahí la
     * pregunta es otra —«¿ya llevo una semana de trabajo detenido?»— y un fin de
     * semana no es demora.
     */
    public function waitingDays(): ?int
    {
        if (! $this->isWaiting() || $this->waiting_since === null) {
            return null;
        }

        return (int) $this->waiting_since->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** ¿Hay algo escrito en las notas? */
    public function hasNotes(): bool
    {
        return is_string($this->description) && trim($this->description) !== '';
    }

    /**
     * Días de diferencia entre el cierre real y el planeado. Positivo es tarde.
     *
     * `null` mientras no haya terminado o no haya plan contra el cual comparar:
     * un cero ahí se leería como «cerró en la fecha», que es una afirmación
     * distinta de «todavía no cierra».
     */
    public function finishDrift(): ?int
    {
        if ($this->actual_finish === null || $this->early_finish === null) {
            return null;
        }

        return (int) $this->early_finish->startOfDay()->diffInDays($this->actual_finish->startOfDay(), false);
    }

    /** El dato puro que consume el motor. */
    public function toNode(?string $calendarKey = null): TaskNode
    {
        $constraintType = ConstraintType::tryFrom((string) $this->constraint_type) ?? ConstraintType::AsSoonAsPossible;

        $date = $this->constraint_date;

        // Una restricción que necesita fecha y no la tiene no significa nada, y
        // aplicarla con una fecha inventada sería peor que ignorarla.
        $constraint = $constraintType->needsDate() && $date instanceof DateTimeInterface
            ? new TaskConstraint($constraintType, DateTimeImmutable::createFromInterface($date))
            : new TaskConstraint($constraintType->needsDate() ? ConstraintType::AsSoonAsPossible : $constraintType);

        return new TaskNode(
            id: (string) $this->id,
            name: (string) $this->name,
            durationMinutes: (int) $this->duration_minutes,
            parentId: $this->parent_id === null ? null : (string) $this->parent_id,
            calendarKey: $calendarKey,
            constraint: $constraint,
            sortOrder: (int) $this->sort_order,
            cost: (float) $this->cost,
            percentComplete: (float) $this->percent_complete,
            requestedStart: $this->requested_start instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($this->requested_start)
                : null,
            deadline: $this->deadline instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($this->deadline)->setTime(23, 59, 59)
                : null,
        );
    }
}
