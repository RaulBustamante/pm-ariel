<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\DocumentIssue;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Emite una versión de un documento: guarda el archivo y lo deja registrado.
 *
 * El número de versión se asigna **dentro de una transacción con bloqueo**. Sin
 * eso, dos personas emitiendo el mismo documento a la vez leerían el mismo
 * «última versión: 3» y las dos intentarían crear la 4 — una fallaría por el
 * índice único y su archivo quedaría huérfano en el disco.
 */
final class DocumentIssuer
{
    /**
     * @param  array<string, mixed>  $summary  las cifras de portada, para leer la lista sin abrir el PDF
     */
    public function issue(
        Project $project,
        string $code,
        string $title,
        string $pdf,
        array $summary = [],
        ?string $notes = null,
    ): DocumentIssue {
        // El archivo se escribe **antes** de la transacción a propósito: si el
        // disco falla, no queda un registro apuntando a un archivo que no está.
        // El caso contrario —archivo sin registro— solo desperdicia bytes.
        $path = "documents/{$project->id}/".Str::uuid()->toString().'.pdf';

        Storage::disk('local')->put($path, $pdf);

        return DB::transaction(function () use ($project, $code, $title, $path, $pdf, $summary, $notes): DocumentIssue {
            $last = DocumentIssue::query()
                ->where('project_id', $project->id)
                ->where('document_code', $code)
                ->lockForUpdate()
                ->max('version');

            return DocumentIssue::query()->create([
                'project_id' => $project->id,
                'document_code' => $code,
                'version' => ((int) $last) + 1,
                'title' => $title,
                'issued_at' => now(),
                'issued_by' => Auth::id(),
                'stored_path' => $path,
                'byte_size' => strlen($pdf),
                // Sirve para saber si dos versiones son el mismo archivo. No para
                // evitar duplicados: el PDF lleva la hora de generación dentro,
                // así que dos emisiones del mismo dato **nunca** dan el mismo
                // resumen. Confiar en eso para deduplicar no funcionaría.
                'checksum' => hash('sha256', $pdf),
                'summary' => $summary,
                'notes' => $notes,
            ]);
        });
    }
}
