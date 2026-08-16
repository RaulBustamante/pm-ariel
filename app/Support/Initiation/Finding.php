<?php

declare(strict_types=1);

namespace App\Support\Initiation;

/**
 * Algo que falta para que el inicio del proyecto esté sano.
 *
 * Siempre trae el porqué. Un semáforo que solo dice "incompleto" obliga al
 * usuario a adivinar, y adivinar es exactamente lo que este recorrido existe
 * para evitar.
 */
final readonly class Finding
{
    public const BLOCKING = 'blocking';

    public const WARNING = 'warning';

    public function __construct(
        public InitiationStep $step,
        public string $severity,
        public string $message,
        public string $why,
    ) {}

    public static function blocking(InitiationStep $step, string $message, string $why): self
    {
        return new self($step, self::BLOCKING, $message, $why);
    }

    public static function warning(InitiationStep $step, string $message, string $why): self
    {
        return new self($step, self::WARNING, $message, $why);
    }

    public function isBlocking(): bool
    {
        return $this->severity === self::BLOCKING;
    }
}
