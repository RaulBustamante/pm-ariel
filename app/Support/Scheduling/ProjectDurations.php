<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use App\Models\Project;
use DateTimeImmutable;

/**
 * El traductor de duraciones con la jornada **de este proyecto**.
 *
 * Existe porque el cálculo estaba copiado en tres lugares —el formulario de
 * tarea, la vista Lista y el importador— y el importador se quedó con la copia
 * vieja. El síntoma es sutil y caro: «3d» en un proyecto de nueve horas diarias
 * se guarda como tres jornadas de ocho, la tarea termina un día antes de lo que
 * el usuario pidió, y nada avisa. Ya pasó dos veces; a la tercera se extrae.
 */
final class ProjectDurations
{
    public static function for(Project $project): DurationParser
    {
        $calendar = $project->calendars()->where('is_default', true)->first()
            ?? $project->calendars()->first();

        if ($calendar === null) {
            return new DurationParser;
        }

        $working = $calendar->toWorkingCalendar();

        return DurationParser::forCalendar(
            $working,
            new DateTimeImmutable('today', $working->timezone()),
        );
    }
}
