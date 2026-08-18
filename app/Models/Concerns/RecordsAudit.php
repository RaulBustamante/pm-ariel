<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Registra quién creó, modificó o eliminó un registro, con el valor anterior y
 * el nuevo. Se engancha a los eventos del modelo para que ningún camino de
 * escritura pueda saltárselo: si se dependiera de llamarlo a mano, el día que
 * alguien olvide hacerlo la bitácora mentirá sin avisar.
 *
 * @mixin Model
 */
trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        /*
        | Quién lo creó y quién lo tocó por última vez.
        |
        | Cuatro modelos decían en su docblock «lo llena RecordsAudit» y **no era
        | cierto**: las columnas existían en cinco tablas y nadie las escribía
        | nunca. No fallaba nada —la pantalla de un documento narrativo llevaba
        | desde la Etapa 7 diciendo «Actualizado el 16/08 por —» y se leía como
        | si el dato faltara, no como si el sistema estuviera roto.
        |
        | Va en `saving` y no en `created`/`updated` porque tiene que quedar en la
        | misma escritura: hacerlo después obligaría a un segundo `save()`, que
        | dispararía otro evento de auditoría por un cambio que nadie hizo.
        */
        static::saving(function (self $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            // Solo si la tabla tiene las columnas. El trait lo usan modelos que
            // no las tienen, y escribirlas reventaría al guardar.
            if (! $model->exists && $model->isFillableAuditColumn('created_by')) {
                $model->setAttribute('created_by', $userId);
            }

            // Al actualizar solo se toca si de verdad cambió algo: un `save()`
            // sin cambios no debería mover «quién lo tocó por última vez».
            if ($model->isFillableAuditColumn('updated_by') && ($model->isDirty() || ! $model->exists)) {
                $model->setAttribute('updated_by', $userId);
            }
        });

        static::created(function (self $model): void {
            $model->writeAuditLog('created', [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            // Un update que solo tocó campos excluidos no es un evento de negocio.
            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->writeAuditLog('updated', $model->auditableAttributes($original), $changes);
        });

        // `restored` e `isForceDeleting` existen en todo modelo de Eloquent, pero
        // solo significan algo en los que borran en suave. Preguntar por el trait
        // es lo que de verdad se quiere saber; preguntar por el método siempre
        // responde que sí.
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive(static::class), strict: true);

        static::deleted(function (self $model) use ($softDeletes): void {
            $event = $softDeletes && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted';

            $model->writeAuditLog($event, $model->auditableAttributes($model->getAttributes()), []);
        });

        if ($softDeletes) {
            static::restored(function (self $model): void {
                $model->writeAuditLog('restored', [], $model->auditableAttributes($model->getAttributes()));
            });
        }
    }

    /**
     * ¿Esta tabla tiene esta columna de autoría?
     *
     * Se pregunta al esquema una sola vez por clase y se recuerda: preguntarlo
     * en cada guardado costaría una consulta al catálogo del motor por cada
     * renglón que se escriba.
     *
     * @var array<string, bool>
     */
    protected static array $auditColumnCache = [];

    protected function isFillableAuditColumn(string $column): bool
    {
        $key = static::class.'::'.$column;

        if (! array_key_exists($key, self::$auditColumnCache)) {
            self::$auditColumnCache[$key] = Schema::connection($this->getConnectionName())
                ->hasColumn($this->getTable(), $column);
        }

        return self::$auditColumnCache[$key];
    }

    /**
     * Campos que nunca deben aparecer en la bitácora. Sobrescribible por modelo.
     *
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return array_merge(
            // `created_by` y `updated_by` los escribe este mismo trait: anotarlos
            // en la bitacora seria auditar la auditoria, y ademas convertiria en
            // evento de negocio un guardado que solo cambio quien lo toco.
            ['password', 'remember_token', 'created_at', 'updated_at', 'created_by', 'updated_by'],
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
