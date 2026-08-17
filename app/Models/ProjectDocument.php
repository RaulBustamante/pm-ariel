<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El contenido redactado de un documento del proyecto.
 *
 * Uno por proyecto y por código de documento. Las secciones viven en `content`
 * como JSON, y **nunca se leen sin pasar por el juego de secciones** que define
 * `config/pmi_sections.php`: eso permite agregar o quitar secciones sin migrar,
 * y que el texto de una sección retirada siga ahí si algún día vuelve.
 *
 * @property array<string, string|null>|null $content
 */
#[Fillable(['project_id', 'document_code', 'content'])]
class ProjectDocument extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quien lo tocó por última vez. Lo llena `RecordsAudit`.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** El texto de una sección, o `null` si todavía no se ha escrito. */
    public function section(string $key): ?string
    {
        $value = $this->content[$key] ?? null;

        // Una cadena vacía y «no escrito» son lo mismo para quien lee el
        // documento, y distinguirlos obligaría a comprobar las dos cosas en cada
        // pantalla.
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
