<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Contracts\Identity\IdentityProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(IdentityProvider $identity): void
    {
        $this->ensureIsNotRateLimited();

        if (! $identity->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            // Un mensaje único para credenciales malas, cuenta inexistente y
            // cuenta desactivada: distinguirlos le confirma a un atacante qué
            // correos existen.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->regenerate();
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    /**
     * Por correo y por IP: limitar solo por correo permitiría bloquear a una
     * persona a propósito; solo por IP dejaría pasar un ataque distribuido.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
