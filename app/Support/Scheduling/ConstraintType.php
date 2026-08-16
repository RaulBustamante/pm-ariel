<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

/**
 * Las ocho restricciones de fecha, de la más flexible a la más rígida.
 *
 * El orden importa al leerlas: `AsSoonAsPossible` no pelea con nada, mientras
 * que `MustStartOn` gana sobre las predecesoras y puede producir holgura
 * negativa. Una restricción rígida es una decisión de negocio disfrazada de
 * dato, y por eso el motor la respeta pero deja el conflicto a la vista en la
 * holgura en vez de esconderlo moviendo la tarea.
 */
enum ConstraintType: string
{
    case AsSoonAsPossible = 'ASAP';
    case AsLateAsPossible = 'ALAP';

    /** No empezar antes de. Empuja hacia adelante; nunca adelanta. */
    case StartNoEarlierThan = 'SNET';

    /** No empezar después de. Solo aprieta el recorrido hacia atrás. */
    case StartNoLaterThan = 'SNLT';

    case FinishNoEarlierThan = 'FNET';
    case FinishNoLaterThan = 'FNLT';

    case MustStartOn = 'MSO';
    case MustFinishOn = 'MFO';

    /** ¿Necesita una fecha para significar algo? */
    public function needsDate(): bool
    {
        return $this !== self::AsSoonAsPossible && $this !== self::AsLateAsPossible;
    }

    /** Rígidas: fijan la fecha, sin negociar con las predecesoras. */
    public function isInflexible(): bool
    {
        return $this === self::MustStartOn || $this === self::MustFinishOn;
    }

    /** ¿Actúa en el recorrido hacia adelante? */
    public function affectsForwardPass(): bool
    {
        return in_array($this, [
            self::StartNoEarlierThan, self::FinishNoEarlierThan,
            self::MustStartOn, self::MustFinishOn,
        ], strict: true);
    }

    /** ¿Actúa en el recorrido hacia atrás? */
    public function affectsBackwardPass(): bool
    {
        return in_array($this, [
            self::StartNoLaterThan, self::FinishNoLaterThan,
            self::MustStartOn, self::MustFinishOn, self::AsLateAsPossible,
        ], strict: true);
    }
}
