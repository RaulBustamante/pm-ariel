<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cuánto enseña el sistema en las pantallas de un proyecto.
 *
 * Es una propiedad **del proyecto**, no de quien lo mira: la misma persona
 * puede llevar un proyecto de tres entregables y otro con ruta crítica y
 * líneas base, y obligarla a escoger un solo nivel para los dos la deja mal
 * atendida en uno de ellos.
 *
 * No es un permiso. Subir el nivel no habilita nada que el rol no permitiera
 * ya; solo deja de esconderlo. Y no se pierde información al bajarlo: lo que
 * se capturó en Especialista sigue ahí y vuelve a aparecer si se sube otra vez.
 *
 * Los nombres se escogieron con cuidado. «Estándar» no insinúa que le falte
 * algo a quien lo usa —es el caso normal, no el caso disminuido— y
 * «Especialista» nombra un oficio, no un rango.
 */
enum DetailLevel: string
{
    /** Fechas, responsables y avance. Lo que casi todo proyecto necesita. */
    case Standard = 'standard';

    /** Agrega holguras, restricciones, tipos de dependencia y demoras. */
    case Specialist = 'specialist';

    /** El nivel con el que nace un proyecto cuando nadie escoge. */
    public static function default(): self
    {
        return self::Standard;
    }

    /**
     * El valor guardado, ya saneado.
     *
     * Un proyecto viejo sin columna, una pantalla en caché que manda algo que
     * no existe o un `null` de la base caen en Estándar en vez de tronar: el
     * daño de enseñar poco es reversible con un clic, el de una excepción en
     * la lista de proyectos no.
     */
    public static function fromNullable(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    public function isSpecialist(): bool
    {
        return $this === self::Specialist;
    }

    /** La etiqueta traducida, para no repetir la clave en cada vista. */
    public function label(): string
    {
        return __("common.detail_{$this->value}");
    }

    /** Para qué sirve este nivel, en una línea. */
    public function help(): string
    {
        return __("projects.detail_{$this->value}_help");
    }
}
