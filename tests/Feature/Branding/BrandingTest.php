<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrandingTest extends TestCase
{
    #[Test]
    public function the_product_name_does_not_appear_anywhere_in_the_source(): void
    {
        // Sin esta verificación en el CI, la regla de portabilidad de marca dura
        // hasta el primer día con prisa.
        $this->artisan('branding:verify')->assertSuccessful();
    }

    #[Test]
    public function the_brand_comes_from_configuration_and_not_from_a_literal(): void
    {
        config()->set('branding.name', 'Cualquier Otro Nombre');

        $this->assertSame('Cualquier Otro Nombre', config('branding.name'));
    }
}
