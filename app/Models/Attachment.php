<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un archivo colgado de una tarea.
 *
 * El nombre en disco lo genera el sistema y no guarda relacion con el que trae
 * el archivo. Guardar el nombre original como ruta permite dos ataques viejos y
 * baratos: escribir fuera de la carpeta con «../», y servir contenido
 * ejecutable porque la extension decia otra cosa.
 */
#[Fillable([
    'project_id', 'task_id', 'original_name', 'stored_path',
    'mime_type', 'extension', 'size_bytes', 'checksum', 'uploaded_by',
])]
class Attachment extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Tamaño legible. 4.2 MB dice mas que 4404019. */
    public function humanSize(): string
    {
        $bytes = (float) $this->size_bytes;

        foreach (['B', 'KB', 'MB'] as $unit) {
            if ($bytes < 1024 || $unit === 'MB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $this->size_bytes.' B';
    }
}
