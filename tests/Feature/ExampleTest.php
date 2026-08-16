<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reemplaza la prueba de ejemplo del esqueleto: la raíz ya no devuelve una
 * página, redirige al panel, y sin sesión el panel manda al inicio de sesión.
 */
final class ExampleTest extends TestCase
{
    #[Test]
    public function the_root_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    #[Test]
    public function an_anonymous_visitor_is_sent_to_the_sign_in_screen(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    #[Test]
    public function the_health_route_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
