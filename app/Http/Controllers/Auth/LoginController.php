<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\Identity\IdentityProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, IdentityProvider $identity): RedirectResponse
    {
        $request->authenticate($identity);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, IdentityProvider $identity): RedirectResponse
    {
        $identity->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
