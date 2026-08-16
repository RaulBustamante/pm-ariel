<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La configuración de un calendario laboral, guardada.
 *
 * El objeto de dominio `WorkingCalendar` es el que sabe calcular; este solo sabe
 * guardarse y reconstruirlo. Mantenerlos separados es lo que permite probar el
 * cálculo sin base de datos.
 *
 * @property array<int|string, list<array{start: string, end: string}>> $week
 * @property array<string, list<array{start: string, end: string}>>|null $exceptions
 */
#[Fillable(['project_id', 'name', 'key', 'timezone', 'week', 'exceptions', 'is_default'])]
class Calendar extends Model
{
    use RecordsAudit, SoftDeletes;

    public const DEFAULT_KEY = 'default';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week' => 'array',
            'exceptions' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Lunes a viernes de 09:00 a 18:00. Lo que se crea con un proyecto nuevo
     * mientras nadie diga otra cosa.
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    public static function standardWeek(): array
    {
        return array_fill_keys(range(1, 5), [['start' => '09:00', 'end' => '18:00']]);
    }

    /** Reconstruye el objeto de dominio que sabe hacer la aritmética. */
    public function toWorkingCalendar(): WorkingCalendar
    {
        return new WorkingCalendar(
            $this->shiftsFrom($this->week),
            $this->shiftsFrom($this->exceptions ?? []),
            new DateTimeZone($this->timezone),
        );
    }

    /**
     * @param  array<int|string, list<array{start: string, end: string}>>  $raw
     * @return array<int|string, list<WorkShift>>
     */
    private function shiftsFrom(array $raw): array
    {
        $result = [];

        foreach ($raw as $key => $shifts) {
            $result[is_numeric($key) ? (int) $key : $key] = array_map(
                fn (array $shift): WorkShift => WorkShift::fromTimes($shift['start'], $shift['end']),
                $shifts,
            );
        }

        return $result;
    }
}
