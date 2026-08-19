<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\DocumentIssue;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * El expediente: el proyecto entero en un solo paquete.
 *
 * **Es lo que convierte setenta documentos sueltos en algo que se entrega.** Un
 * proyecto que cierra no se entrega abriendo setenta pantallas y descargando
 * setenta PDF: se entrega una vez, y quien lo reciba tiene que poder abrirlo
 * dentro de cinco años sin este sistema.
 *
 * Por eso el paquete lleva **un índice legible** además de los archivos. Un ZIP
 * con setenta nombres crípticos es un respaldo, no un expediente; el índice dice
 * qué es cada cosa, cuándo se emitió y quién la emitió, y se lee en cualquier
 * navegador.
 *
 * Lo que va dentro son las **versiones emitidas** (7.1), no lo que el sistema
 * generaría hoy. Un expediente que se regenera al abrirlo diría cosas distintas
 * cada vez que se abre, y entonces no prueba nada — que es exactamente lo que un
 * expediente existe para hacer.
 */
final class ProjectArchive
{
    /**
     * Arma el ZIP y devuelve su ruta temporal.
     *
     * @throws RuntimeException si el sistema no puede escribir el paquete
     */
    public function build(Project $project): string
    {
        $issues = DocumentIssue::query()
            ->where('project_id', $project->id)
            ->with('issuedBy')
            ->orderBy('document_code')
            ->orderBy('version')
            ->get();

        // Se escribe en el directorio de trabajo del framework y no en
        // `storage/app`: ahí es donde vive lo que se respalda, y un paquete de
        // cien megas dentro de lo respaldado se copiaría a sí mismo. Es la misma
        // guarda que se puso al respaldo en la Etapa 1.
        $directory = storage_path('framework/archives');

        if (! is_dir($directory) && ! mkdir($directory, recursive: true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el directorio del expediente.');
        }

        $path = $directory.DIRECTORY_SEPARATOR.$project->code.'-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el expediente.');
        }

        $disk = Storage::disk('local');
        $included = [];
        $missing = [];

        foreach ($issues as $issue) {
            $stored = (string) $issue->stored_path;

            // Un archivo que ya no está en el disco **se anota como faltante**
            // en el índice en vez de romper el paquete. Un expediente que no se
            // puede armar porque falta un PDF de hace dos años es peor que uno
            // que dice cuál falta.
            if (! $disk->exists($stored)) {
                $missing[] = $issue;

                continue;
            }

            $name = sprintf(
                '%s/%s-v%d.pdf',
                __("documents.group_{$this->groupOf((string) $issue->document_code)}"),
                (string) $issue->document_code,
                (int) $issue->version,
            );

            $zip->addFromString($name, (string) $disk->get($stored));
            $included[] = ['issue' => $issue, 'name' => $name];
        }

        $zip->addFromString('INDICE.html', $this->index($project, $included, $missing));

        $zip->close();

        return $path;
    }

    /**
     * ¿Cuántas versiones emitidas hay para archivar?
     *
     * Se pregunta antes de ofrecer el botón: un expediente vacío se descarga,
     * se abre, y solo entonces se descubre que no había nada emitido.
     */
    public function countIssued(Project $project): int
    {
        return DocumentIssue::query()->where('project_id', $project->id)->count();
    }

    /**
     * El índice del paquete, en HTML plano.
     *
     * Sin hoja de estilos externa y sin scripts: tiene que abrirse dentro de
     * cinco años, en cualquier máquina, sin este sistema y sin internet.
     *
     * @param  list<array{issue: DocumentIssue, name: string}>  $included
     * @param  list<DocumentIssue>  $missing
     */
    private function index(Project $project, array $included, array $missing): string
    {
        $rows = '';

        foreach ($included as $entry) {
            $issue = $entry['issue'];

            $rows .= sprintf(
                '<tr><td>%s</td><td>v%d</td><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
                e(__("documents.doc_{$issue->document_code}")),
                (int) $issue->version,
                e($issue->issued_at?->format('d/m/Y H:i') ?? ''),
                e($this->issuerName($issue)),
                e($entry['name']),
                e($entry['name']),
            );
        }

        $missingRows = '';

        foreach ($missing as $issue) {
            $missingRows .= sprintf(
                '<tr class="falta"><td>%s</td><td>v%d</td><td>%s</td><td colspan="2">%s</td></tr>',
                e(__("documents.doc_{$issue->document_code}")),
                (int) $issue->version,
                e($issue->issued_at?->format('d/m/Y H:i') ?? ''),
                e(__('archive.file_missing')),
            );
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="utf-8">
        <title>{$this->esc($project->code)} · {$this->esc(__('archive.title'))}</title>
        <style>
        body { font-family: system-ui, sans-serif; margin: 40px; color: #0f172a; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .clave { font-family: monospace; color: #475569; }
        p.nota { max-width: 40em; color: #475569; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 14px; }
        th { text-align: left; border-bottom: 2px solid #94a3b8; padding: 6px 8px; font-size: 12px;
             text-transform: uppercase; letter-spacing: .5px; color: #475569; }
        td { border-bottom: 1px solid #e2e8f0; padding: 6px 8px; }
        tr.falta td { color: #b91c1c; }
        </style>
        </head>
        <body>
        <h1>{$this->esc($project->name)}</h1>
        <div class="clave">{$this->esc($project->code)} · {$this->esc(__('archive.generated', ['date' => now()->format('d/m/Y H:i')]))}</div>
        <p class="nota">{$this->esc(__('archive.index_note'))}</p>
        <table>
        <thead><tr>
        <th>{$this->esc(__('documents.title'))}</th>
        <th>{$this->esc(__('documents.version'))}</th>
        <th>{$this->esc(__('documents.issued_on'))}</th>
        <th>{$this->esc(__('documents.issued_by'))}</th>
        <th>{$this->esc(__('archive.file'))}</th>
        </tr></thead>
        <tbody>{$rows}{$missingRows}</tbody>
        </table>
        </body>
        </html>
        HTML;
    }

    /**
     * Quien emitio, o una raya si su cuenta ya no existe.
     *
     * Se pregunta por el objeto y no con `?->`: la cuenta se anula al borrarse
     * (`nullOnDelete`), pero el analisis estatico tipa la relacion como no nula.
     */
    private function issuerName(DocumentIssue $issue): string
    {
        $user = $issue->issuedBy;

        return $user === null ? '—' : (string) $user->name;
    }

    private function esc(string $value): string
    {
        return e($value);
    }

    private function groupOf(string $code): string
    {
        return (string) config("pmi_documents.catalogue.{$code}.group", 'executing');
    }
}
