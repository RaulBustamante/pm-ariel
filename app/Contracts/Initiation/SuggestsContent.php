<?php

declare(strict_types=1);

namespace App\Contracts\Initiation;

use App\Models\Project;
use App\Models\Stakeholder;

/**
 * De dónde salen las sugerencias del recorrido de inicio.
 *
 * Hoy la única implementación son plantillas y reglas (D-016): un catálogo de
 * riesgos típicos por tipo de proyecto y la estrategia que corresponde a cada
 * cuadrante de la matriz poder/interés. No hay ninguna llamada a un modelo.
 *
 * El día que se decida proveedor y presupuesto, se liga otra implementación en
 * el contenedor y nada más cambia — mismo patrón que `IdentityProvider` para el
 * SSO. Por eso `isAvailable()` existe: la interfaz muestra el botón de sugerir
 * solo si hay algo detrás que sugiera de verdad.
 */
interface SuggestsContent
{
    /**
     * Riesgos típicos de este tipo de proyecto, aún no guardados.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestRisks(Project $project): array;

    /**
     * Papeles de interesado que casi siempre existen, aún no guardados.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestStakeholders(Project $project): array;

    /**
     * @return list<string>
     */
    public function suggestDeliverables(Project $project): array;

    /**
     * Un borrador para un campo de texto del acta — el problema, el beneficio,
     * los objetivos. Devuelve null si no hay nada que proponer.
     *
     * Con plantillas es un ejemplo del tipo de proyecto; con un modelo es un
     * borrador redactado a partir de lo que el usuario ya escribió. En los dos
     * casos el usuario lo revisa y lo corrige: nunca se guarda solo.
     */
    public function suggestNarrative(Project $project, string $field): ?string;

    /**
     * Cómo tratar a este interesado, según dónde cayó en la matriz.
     */
    public function suggestEngagementStrategy(Stakeholder $stakeholder): string;

    /**
     * ¿Hay algo que sugerir? Con plantillas depende de que el proyecto tenga
     * una; con un modelo dependerá de que haya credenciales configuradas.
     */
    public function isAvailable(Project $project): bool;
}
