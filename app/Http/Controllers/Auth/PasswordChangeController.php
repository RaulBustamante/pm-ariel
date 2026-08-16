<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', __('auth.password_updated'));
    }
}
