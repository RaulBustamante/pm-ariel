<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

/**
 * Los cuatro tipos de dependencia. Se leen "qué extremo de la predecesora
 * gobierna qué extremo de la sucesora".
 */
enum DependencyType: string
{
    /** Fin a inicio: la sucesora no empieza hasta que la predecesora termina. */
    case FinishToStart = 'FS';

    /** Inicio a inicio: no empieza hasta que la otra empieza. */
    case StartToStart = 'SS';

    /** Fin a fin: no termina hasta que la otra termina. */
    case FinishToFinish = 'FF';

    /** Inicio a fin: no termina hasta que la otra empieza. La rara, y existe. */
    case StartToFinish = 'SF';

    /** ¿La restricción cae sobre el inicio de la sucesora o sobre su fin? */
    public function drivesSuccessorStart(): bool
    {
        return $this === self::FinishToStart || $this === self::StartToStart;
    }

    /** ¿Se mide desde el fin de la predecesora o desde su inicio? */
    public function readsPredecessorFinish(): bool
    {
        return $this === self::FinishToStart || $this === self::FinishToFinish;
    }
}
