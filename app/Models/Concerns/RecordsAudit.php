<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra quién creó, modificó o eliminó un registro, con el valor anterior y
 * el nuevo. Se engancha a los eventos del modelo para que ningún camino de
 * escritura pueda saltárselo: si se dependiera de llamarlo a mano, el día que
 * alguien olvide hacerlo la bitácora mentirá sin avisar.
 */
trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created', [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            // Un update que solo tocó campos excluidos no es un evento de negocio.
            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->writeAuditLog('updated', $model->auditableAttributes($original), $changes);
        });

        static::deleted(function (Model $model): void {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted';

            $model->writeAuditLog($event, $model->auditableAttributes($model->getAttributes()), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                $model->writeAuditLog('restored', [], $model->auditableAttributes($model->getAttributes()));
            });
        }
    }

    /**
     * Campos que nunca deben aparecer en la bitácora. Sobrescribible por modelo.
     *
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->auditExcluded()));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAuditLog(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'auditable_type' => $this::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500) ?: null,
        ]);
    }
}
