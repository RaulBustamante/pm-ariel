<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buzon_ticket_id', 'ruta_archivo', 'nombre_original', 'tamano'])]
final class BuzonAdjunto extends Model
{
    protected $table = 'buzon_adjuntos';

    /** @return BelongsTo<BuzonTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(BuzonTicket::class, 'buzon_ticket_id');
    }

    public function tamanoLegible(): string
    {
        return $this->tamano < 1048576
            ? round($this->tamano / 1024).' KB'
            : round($this->tamano / 1048576, 1).' MB';
    }
}
