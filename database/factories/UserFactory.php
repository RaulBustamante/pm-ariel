<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // Estos dos tienen valor por omisión en la migración, pero un modelo
            // recién creado por la fábrica no los trae cargados: quedan en null
            // hasta que alguien lo relea de la base. Cualquier código que decida
            // algo con ellos —el guardia de cuenta activa, el de contraseña
            // temporal— ve null y actúa como si fueran falsos.
            //
            // Declararlos aquí hace que el usuario de prueba se parezca al que
            // existe de verdad, en vez de obligar a cada prueba a acordarse.
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
