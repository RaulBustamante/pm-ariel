<?php

declare(strict_types=1);

namespace App\Contracts\Scheduling;

use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;

/**
 * De dónde salen los días y horas que rompen la jornada normal.
 *
 * Hoy la única implementación lee los feriados y excepciones capturados a mano
 * en la base. Mañana puede haber una que lea el calendario de Google o el de
 * Outlook de cada persona y devuelva sus bloques ocupados como excepciones.
 *
 * La costura existe desde ahora aunque la integración no: agregarla después
 * significaría tocar `WorkingCalendar`, que es la clase de la que depende todo
 * el cálculo, y eso es exactamente lo que no conviene mover con el motor ya en
 * producción. Mismo patrón que `IdentityProvider` para el SSO.
 *
 * Nota de diseño para quien construya la integración: un evento externo se
 * traduce a **ausencia de turnos**, no a una tarea. El motor no necesita saber
 * que alguien tiene una junta; le basta con saber que esas dos horas no cuentan
 * como tiempo de trabajo.
 */
interface ProvidesCalendarExceptions
{
    /**
     * Excepciones del calendario `$calendarKey` entre las dos fechas.
     *
     * La clave de cada entrada es la fecha en formato `Y-m-d`; el valor, los
     * turnos que sí se trabajan ese día. Una lista vacía significa día no
     * laborable — que es como se representa tanto un feriado como un día
     * completamente ocupado por eventos externos.
     *
     * @return array<string, list<WorkShift>>
     */
    public function exceptionsFor(string $calendarKey, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
