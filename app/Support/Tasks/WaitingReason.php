<?php

declare(strict_types=1);

namespace App\Support\Tasks;

/**
 * Por qué una tarea no avanza, cuando la razón está afuera.
 *
 * Es un eje **aparte del avance**, no un cuarto valor suyo. `Task::state()` sale
 * de `percent_complete` y contesta cuánto se hizo (decisión CL-004); esto
 * contesta por qué está detenido. Las dos cosas son ciertas a la vez: una tarea
 * al 85 % cuyas pruebas ya se enviaron está en curso *y* esperando, y haber
 * metido la espera como estado habría borrado ese 85 % — que es justo la
 * discrepancia entre pantallas que CL-004 existe para evitar.
 *
 * Son cinco y catalogadas, no texto libre: así se pueden contar, filtrar y
 * traducir. Un menú de doce opciones que nadie llena bien es peor que cinco que
 * significan algo; lo que no cabe en el catálogo va en la nota.
 *
 * **No mueve ninguna fecha.** Es información para dar seguimiento, no entrada
 * del cálculo de ruta crítica: capturar un dato informativo no debería
 * recalcular el plan.
 *
 * Los nombres visibles no viven aquí, están en los archivos `tasks.php` de
 * cada idioma (D-004).
 */
enum WaitingReason: string
{
    /** En el escritorio de alguien, esperando una firma. */
    case Approval = 'approval';

    /**
     * El usuario está probando.
     *
     * Va aparte de `Approval` aunque acabe en una aprobación: en UAT *están
     * probando* y en aprobación *está detenido en un escritorio*. El seguimiento
     * que hay que hacer es distinto, y por eso se distinguen.
     */
    case UserTesting = 'uat';

    case ClientResponse = 'client';

    /** Otra área, un proveedor, quien da de alta en el sistema. */
    case ThirdParty = 'third_party';

    /** Un impedimento que no es la respuesta de nadie en particular. */
    case Blocked = 'blocked';

    /**
     * Los valores válidos, para la validación y para pintar el menú.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
