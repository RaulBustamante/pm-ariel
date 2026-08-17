<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Project;
use App\Models\ProjectDocument;

/**
 * El motor de los documentos que se redactan.
 *
 * **Uno para los veinticinco.** Junta tres cosas que viven separadas a
 * propósito: el catálogo dice qué documentos existen y qué juego de secciones
 * usa cada uno (`config/pmi_documents.php`), el juego dice qué secciones son y
 * cuáles hacen falta (`config/pmi_sections.php`), y la base guarda el texto.
 *
 * Separarlas es lo que permite agregar un documento nuevo con **una línea de
 * configuración** en vez de una pantalla, una migración y una plantilla. Y es lo
 * que evita que trece planes de gestión se desincronicen: la sección de umbrales
 * se define una vez.
 *
 * Nada obliga a llenar todo para guardar. Lo que falta se señala; obligar a
 * completar de golpe solo consigue que alguien invente texto para poder avanzar,
 * y un plan con relleno es peor que uno con huecos visibles.
 */
final class NarrativeDocument
{
    /**
     * ¿Este código es un documento que se redacta y ya tiene sus secciones?
     *
     * Se piden las dos cosas: un documento marcado como narrativo sin juego de
     * secciones abriría un formulario vacío, que es peor que decir que todavía
     * no está.
     */
    public function isNarrative(string $code): bool
    {
        $entry = $this->entry($code);

        return ($entry['kind'] ?? null) === 'narrative'
            && is_string($entry['sections'] ?? null);
    }

    /**
     * Las secciones de un documento, con su estado.
     *
     * @return list<array{key: string, title: string, help: string, required: bool, rows: int, value: ?string}>
     */
    public function sections(string $code, ?ProjectDocument $document = null): array
    {
        $set = $this->entry($code)['sections'] ?? null;

        if (! is_string($set)) {
            return [];
        }

        /** @var array<string, array{required: bool, rows: int}> $definition */
        $definition = config("pmi_sections.sets.{$set}", []);

        $sections = [];

        foreach ($definition as $key => $meta) {
            $sections[] = [
                'key' => $key,
                'title' => __("sections.title_{$key}"),
                'help' => __("sections.help_{$key}"),
                'required' => (bool) $meta['required'],
                'rows' => (int) $meta['rows'],
                'value' => $document?->section($key),
            ];
        }

        return $sections;
    }

    /**
     * Cuántas secciones necesarias siguen vacías.
     *
     * Es lo que pinta el estado en el tablero. Se cuentan solo las marcadas como
     * necesarias: señalar como incompleto un documento al que le falta una
     * sección opcional entrena a la gente a ignorar el aviso.
     */
    public function missing(string $code, ?ProjectDocument $document): int
    {
        $missing = 0;

        foreach ($this->sections($code, $document) as $section) {
            if ($section['required'] && $section['value'] === null) {
                $missing++;
            }
        }

        return $missing;
    }

    /** El documento guardado, o `null` si nunca se ha redactado. */
    public function of(Project $project, string $code): ?ProjectDocument
    {
        return ProjectDocument::query()
            ->where('project_id', $project->id)
            ->where('document_code', $code)
            ->first();
    }

    /**
     * Guarda solo las secciones que **pertenecen al juego** de este documento.
     *
     * Filtrar aquí y no confiar en el formulario es lo que impide que una
     * petición armada a mano meta llaves arbitrarias en el JSON — y de paso deja
     * intacto el texto de una sección que se haya retirado del juego, por si
     * vuelve.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(Project $project, string $code, array $input): ProjectDocument
    {
        $document = $this->of($project, $code);
        // Explicito y no `?->` a la izquierda de `??`: se distingue
        // <<no hay documento>> de <<el documento no tiene contenido>>, que son
        // dos cosas distintas aunque den el mismo arreglo vacio.
        $content = $document === null ? [] : ($document->content ?? []);

        foreach ($this->sections($code, $document) as $section) {
            $value = $input[$section['key']] ?? null;

            $content[$section['key']] = is_string($value) && trim($value) !== ''
                ? trim($value)
                : null;
        }

        return ProjectDocument::query()->updateOrCreate(
            ['project_id' => $project->id, 'document_code' => $code],
            ['content' => $content],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $code): array
    {
        /** @var array<string, mixed> $entry */
        $entry = config("pmi_documents.catalogue.{$code}", []);

        return $entry;
    }
}
