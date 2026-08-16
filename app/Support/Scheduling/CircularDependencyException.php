<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use RuntimeException;

/**
 * Lleva el ciclo consigo, no solo la noticia de que existe.
 *
 * "Hay una dependencia circular" en un proyecto de 800 tareas es inservible:
 * quien lo lee tiene que buscarla a mano. "A → B → C → A" se corrige en un
 * minuto. Encontrarlo cuesta lo mismo en el algoritmo; no devolverlo es una
 * decisión, y es la equivocada.
 */
final class CircularDependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $cycle  El ciclo, cerrado: el primer id se repite al final.
     */
    public function __construct(public readonly array $cycle)
    {
        parent::__construct('Dependencia circular: '.implode(' → ', $cycle));
    }

    /**
     * @return list<string>
     */
    public function cycle(): array
    {
        return $this->cycle;
    }
}
