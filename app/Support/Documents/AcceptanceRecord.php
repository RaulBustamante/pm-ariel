<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Project;
use App\Models\ProjectRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El motor de las actas de aceptación. La cuarta y última especie.
 *
 * Dos documentos —la aceptación de un entregable y la del proyecto entero— con
 * una sola maquinaria, por la misma razón que los veinticinco narrativos y los
 * catorce registros: tienen la misma forma, y lo que cambia entre uno y otro
 * cabe en `config/pmi_records.php`.
 *
 * **Lo que hace distinta a un acta es el final.** Un registro crece durante el
 * proyecto; un acta cierra algo y se congela. Mientras no esté firmada es un
 * borrador que se corrige; al firmarse queda inmutable, y el PDF se **emite y
 * archiva** con el motor del bloque 7.1 — así el acta tiene número de versión,
 * fecha y huella, y quien la busque dentro de un año la encuentra tal como se
 * firmó.
 *
 * **No es una firma electrónica.** El sistema no la tiene y no finge tenerla: lo
 * que queda registrado es que alguien con nombre y puesto aceptó en una fecha, y
 * quién lo asentó. La pantalla lo dice con esas palabras, porque un sello que
 * promete más de lo que vale es peor que no tener sello.
 */
final class AcceptanceRecord
{
    public function __construct(
        private readonly DocumentIssuer $issuer,
    ) {}

    /**
     * ¿Este código es un acta y ya tiene su definición?
     *
     * Se piden las dos cosas, igual que en los otros motores: un documento
     * marcado como acta sin definición abriría una pantalla sin prefijo con el
     * que numerar, que es peor que decir que todavía no está.
     */
    public function isRecord(string $code): bool
    {
        return (string) config("pmi_documents.catalogue.{$code}.kind") === 'record'
            && is_array(config("pmi_records.types.{$code}"));
    }

    /** ¿Este tipo de acta apunta a un entregable del plan? */
    public function linksDeliverable(string $code): bool
    {
        return (bool) config("pmi_records.types.{$code}.links_deliverable", false);
    }

    /**
     * @return list<string>
     */
    public function decisions(): array
    {
        /** @var list<string> $decisions */
        $decisions = config('pmi_records.decisions', []);

        return $decisions;
    }

    /**
     * Las actas de un tipo, de la más reciente a la más vieja.
     *
     * @return Collection<int, ProjectRecord>
     */
    public function all(Project $project, string $code): Collection
    {
        return ProjectRecord::query()
            ->with(['task', 'signedBy'])
            ->where('project_id', $project->id)
            ->where('document_code', $code)
            ->orderByDesc('accepted_on')
            ->orderByDesc('sequence')
            ->get();
    }

    /**
     * Cuántas hay, cuántas firmadas y cuántas siguen en borrador.
     *
     * Un acta sin firmar **no vale**: es lo único que hay que poder ver de un
     * vistazo, porque una lista de actas donde tres son borradores y se ven
     * igual que las firmadas hace creer que el proyecto está más cerrado de lo
     * que está.
     *
     * @return array{total: int, signed: int, draft: int, rejected: int}
     */
    public function summary(Project $project, string $code): array
    {
        $base = ProjectRecord::query()
            ->where('project_id', $project->id)
            ->where('document_code', $code);

        return [
            'total' => (clone $base)->count(),
            'signed' => (clone $base)->whereNotNull('signed_at')->count(),
            'draft' => (clone $base)->whereNull('signed_at')->count(),
            'rejected' => (clone $base)->where('decision', ProjectRecord::REJECTED)->count(),
        ];
    }

    /**
     * Levanta un acta y le asigna su número.
     *
     * El número se asigna **dentro de una transacción con bloqueo**, por la
     * misma razón que la versión de un documento emitido y el número de un
     * registro: sin eso, dos personas levantando un acta a la vez leerían el
     * mismo «va en la 3» y una fallaría por el índice único.
     *
     * @param  array<string, mixed>  $input
     */
    public function open(Project $project, string $code, array $input): ProjectRecord
    {
        return DB::transaction(function () use ($project, $code, $input): ProjectRecord {
            $last = ProjectRecord::query()
                ->withTrashed()
                ->where('project_id', $project->id)
                ->where('document_code', $code)
                ->lockForUpdate()
                ->max('sequence');

            $data = $this->clean($code, $input);

            return ProjectRecord::query()->create([
                'project_id' => $project->id,
                'document_code' => $code,
                'sequence' => ((int) $last) + 1,
                'subject' => $data['subject'],
                'detail' => $data['detail'],
                'task_id' => $data['task_id'],
                'decision' => $data['decision'],
                'reservations' => $data['reservations'],
                'accepted_by_name' => $data['accepted_by_name'],
                'accepted_by_role' => $data['accepted_by_role'],
                'accepted_by_org' => $data['accepted_by_org'],
                'accepted_on' => $data['accepted_on'],
            ]);
        });
    }

    /**
     * Corrige un borrador. Una firmada rechaza el cambio en el modelo, así que
     * aquí se comprueba antes solo para poder decirlo con una pantalla en vez de
     * con un error de servidor.
     *
     * @param  array<string, mixed>  $input
     */
    public function amend(ProjectRecord $record, array $input): ProjectRecord
    {
        if ($record->isSigned()) {
            throw new RuntimeException('Un acta firmada no se edita.');
        }

        $record->update($this->clean((string) $record->document_code, $input));

        return $record;
    }

    /**
     * Firma el acta: la congela y **archiva su PDF**.
     *
     * El archivo se escribe antes de marcar la firma. Si se hiciera al revés y
     * fallara la emisión, quedaría un acta congelada sin documento que enseñar y
     * sin forma de volver a generarlo, porque ya no se puede tocar.
     *
     * @param  callable(ProjectRecord): string  $render  produce el PDF del acta
     */
    public function sign(ProjectRecord $record, callable $render): ProjectRecord
    {
        if ($record->isSigned()) {
            throw new RuntimeException('Esta acta ya está firmada.');
        }

        /** @var Project $project */
        $project = $record->project()->firstOrFail();

        $this->issuer->issue(
            $project,
            (string) $record->document_code,
            $record->reference().' · '.$record->subject,
            $render($record),
            [
                'reference' => $record->reference(),
                'decision' => $record->decision,
                'accepted_by' => $record->accepted_by_name,
                'accepted_on' => $record->accepted_on?->toDateString(),
            ],
        );

        $record->forceFill([
            'signed_at' => now(),
            'signed_by' => Auth::id(),
            'checksum' => $record->fingerprint(),
        ])->save();

        return $record;
    }

    /**
     * Se queda solo con lo que pertenece a este tipo de acta.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     subject: string, detail: ?string, task_id: ?int, decision: string,
     *     reservations: ?string, accepted_by_name: string, accepted_by_role: ?string,
     *     accepted_by_org: ?string, accepted_on: string
     * }
     */
    private function clean(string $code, array $input): array
    {
        $decisions = $this->decisions();
        $decision = (string) ($input['decision'] ?? '');

        return [
            'subject' => trim((string) ($input['subject'] ?? '')),
            'detail' => $this->text($input['detail'] ?? null),
            // El vínculo al entregable solo se guarda donde el tipo lo pide: en
            // el acta del proyecto entero no significa nada y obligaría a
            // escoger una tarea arbitraria.
            'task_id' => $this->linksDeliverable($code) && ($input['task_id'] ?? null) !== null
                ? (int) $input['task_id']
                : null,
            'decision' => in_array($decision, $decisions, strict: true) ? $decision : ($decisions[0] ?? 'accepted'),
            // Las reservas solo tienen sentido cuando se aceptó con ellas o se
            // rechazó. Guardarlas en una aceptación limpia dejaría un texto que
            // contradice la decisión que está al lado.
            'reservations' => $decision === ProjectRecord::ACCEPTED
                ? null
                : $this->text($input['reservations'] ?? null),
            'accepted_by_name' => trim((string) ($input['accepted_by_name'] ?? '')),
            'accepted_by_role' => $this->text($input['accepted_by_role'] ?? null),
            'accepted_by_org' => $this->text($input['accepted_by_org'] ?? null),
            'accepted_on' => $this->text($input['accepted_on'] ?? null) ?? now()->toDateString(),
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
