<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cómo ve cada quien el sistema: idioma, zona horaria y nivel de detalle.
 *
 * Son preferencias de presentación, no permisos. Poner Modo Experto no
 * habilita nada que el rol no permitiera ya; solo deja de esconderlo.
 */
final class PreferencesController extends Controller
{
    public function edit(): View
    {
        return view('preferences.edit');
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update($request->validated());

        // El idioma nuevo debe verse ya en el mensaje de confirmación, no en la
        // siguiente pantalla: el middleware que lo aplica ya corrió.
        app()->setLocale($user->locale);

        return redirect()
            ->route('preferences.edit')
            ->with('status', __('preferences.saved'));
    }
}
