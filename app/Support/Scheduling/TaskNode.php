<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * Una tarea, como dato puro. Sin Eloquent, sin base de datos, sin fechas
 * calculadas: solo lo que el usuario decidió.
 *
 * El motor trabaja sobre estos objetos y devuelve otros. Que no toquen la base
 * es lo que permite probar el cálculo con casos de papel — y calcular un
 * escenario hipotético sin escribir nada.
 */
final readonly class TaskNode
{
    public function __construct(
        public string $id,
        public string $name = '',
        /** Minutos de trabajo. Cero es un hito. */
        public int $durationMinutes = 0,
        public ?string $parentId = null,
        /** Calendario propio; si es null, se usa el del proyecto. */
        public ?string $calendarKey = null,
        public ?TaskConstraint $constraint = null,
        /** Orden entre hermanas. Define la numeración WBS. */
        public int $sortOrder = 0,
        /** Solo para el rollup de resumen: costo y avance de la hoja. */
        public float $cost = 0.0,
        public float $percentComplete = 0.0,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Una tarea necesita identificador.');
        }

        if ($durationMinutes < 0) {
            throw new InvalidArgumentException("La tarea {$id} tiene duración negativa.");
        }

        if ($percentComplete < 0 || $percentComplete > 100) {
            throw new InvalidArgumentException("El avance de {$id} está fuera de 0–100.");
        }
    }

    public function isMilestone(): bool
    {
        return $this->durationMinutes === 0;
    }

    public function constraint(): TaskConstraint
    {
        return $this->constraint ?? TaskConstraint::asSoonAsPossible();
    }
}
