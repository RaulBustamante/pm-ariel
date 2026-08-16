<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El punto de partida de un tipo de proyecto: qué suele salir mal, a quién suele
 * importarle y qué se suele entregar.
 *
 * Vive en la base y no en un archivo de configuración porque quien conoce el
 * negocio debe poder ajustarlo sin pedir un despliegue.
 *
 * @property array<string, mixed>|null $payload
 */
#[Fillable(['key', 'name', 'description', 'is_system', 'payload', 'sort_order'])]
class ProjectTemplate extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'payload' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProjectCharter, $this>
     */
    public function charters(): HasMany
    {
        return $this->hasMany(ProjectCharter::class, 'template_id');
    }

    /**
     * Riesgos típicos del tipo de proyecto, tal como se precargan.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogRisks(): array
    {
        $risks = $this->payload['risks'] ?? [];

        return is_array($risks) ? array_values($risks) : [];
    }

    /**
     * Papeles de interesado que casi siempre existen en este tipo de proyecto.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogStakeholders(): array
    {
        $stakeholders = $this->payload['stakeholders'] ?? [];

        return is_array($stakeholders) ? array_values($stakeholders) : [];
    }

    /**
     * @return list<string>
     */
    public function catalogDeliverables(): array
    {
        $deliverables = $this->payload['deliverables'] ?? [];

        return is_array($deliverables) ? array_values(array_map(strval(...), $deliverables)) : [];
    }
}
