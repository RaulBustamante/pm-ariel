<?php

declare(strict_types=1);

namespace App\Support\Initiation;

/**
 * Los cuatro pasos del recorrido de inicio, en orden.
 *
 * Un enum y no una tabla: los pasos son estructura del producto, no datos que
 * el usuario configure. El día que se agregue uno, se agrega aquí y las
 * pantallas lo recogen solas.
 */
enum InitiationStep: string
{
    case Justification = 'justification';
    case Stakeholders = 'stakeholders';
    case Charter = 'charter';
    case Risks = 'risks';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    public function position(): int
    {
        return array_search($this, self::ordered(), strict: true) + 1;
    }

    public function next(): ?self
    {
        return self::ordered()[$this->position()] ?? null;
    }

    public function previous(): ?self
    {
        return self::ordered()[$this->position() - 2] ?? null;
    }

    public function title(): string
    {
        return __("initiation.step_{$this->value}_title");
    }

    /** Una línea de para qué sirve el paso, en el idioma del usuario. */
    public function purpose(): string
    {
        return __("initiation.step_{$this->value}_purpose");
    }

    public function route(): string
    {
        return "projects.initiation.{$this->value}";
    }
}
