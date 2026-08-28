<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Identity\ProvisionsUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetUserPasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La contraseña de alguien más se toca en su propio formulario, aparte del
 * alta y de la edición de perfil: un descuido al guardar el área no puede
 * dejar a nadie fuera de su cuenta.
 *
 * Existe porque el camino de "olvidé mi contraseña" depende del correo, y
 * mientras el correo no salga del servidor cada olvido se vuelve un acceso
 * por consola (supuesto S-02).
 */
final class UserPasswordController extends Controller
{
    /**
     * El administrador dicta la contraseña. Útil cuando hay que dársela a la
     * persona por otro canal en ese momento.
     */
    public function update(SetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $mustChange = (bool) $request->validated('must_change_password');

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'must_change_password' => $mustChange,
        ])->save();

        $this->recordAudit($request, $user, 'password_set', $mustChange);

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('users.password_set', ['email' => $user->email]));
    }

    /**
     * El sistema genera una temporal y la muestra una sola vez, igual que en
     * el alta.
     */
    public function reset(Request $request, User $user, ProvisionsUsers $provisioner): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $password = $provisioner->resetPassword($user);

        // Con SSO no hay contraseña que reiniciar: la administra el proveedor.
        if ($password === null) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', __('users.password_not_managed', ['email' => $user->email]));
        }

        $this->recordAudit($request, $user, 'password_reset', true);

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('users.password_reset_with_password', [
                'email' => $user->email,
                'password' => $password,
            ]));
    }

    /**
     * La bitácora excluye `password` a propósito, así que un cambio de
     * contraseña no dejaría rastro por sí solo. Se anota el hecho y quién lo
     * hizo; el valor no se escribe en ningún lado.
     */
    private function recordAudit(Request $request, User $user, string $event, bool $mustChange): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'auditable_type' => $user::class,
            'auditable_id' => $user->getKey(),
            'event' => $event,
            'old_values' => null,
            'new_values' => ['must_change_password' => $mustChange],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);
    }
}
