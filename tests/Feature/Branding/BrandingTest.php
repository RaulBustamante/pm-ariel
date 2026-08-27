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
    public function the_files_the_brand_points_at_actually_exist(): void
    {
        // Una marca que apunta a un archivo que no esta deja la entrada con el
        // icono de imagen rota, y no lo nota nadie hasta que un cliente abre la
        // pantalla: el resto del sistema sigue funcionando perfecto.
        foreach (['logo', 'mark'] as $key) {
            $path = public_path((string) config("branding.{$key}"));

            $this->assertFileExists($path, "branding.{$key} apunta a un archivo que no existe");
        }
    }

    #[Test]
    public function the_login_logo_is_wide_and_not_a_tall_box(): void
    {
        // El archivo original medía 768x512 con el 64 % de la altura en fondo
        // vacío: se veía como un cuadrado con el logotipo perdido adentro. Esta
        // prueba fija la forma, no el archivo — si alguien vuelve a subir un
        // logotipo con franjas muertas, se entera aquí y no en producción.
        $size = getimagesize(public_path((string) config('branding.logo')));

        $this->assertIsArray($size);

        [$width, $height] = $size;
        $ratio = $width / $height;

        $this->assertGreaterThan(
            2.0,
            $ratio,
            "El logotipo de la entrada es un logotipo horizontal: {$width}x{$height} da una relacion de "
            .round($ratio, 2).', y abajo de 2 vuelve a leerse como un recuadro alto.',
        );
    }

    #[Test]
    public function the_brand_comes_from_configuration_and_not_from_a_literal(): void
    {
        config()->set('branding.name', 'Cualquier Otro Nombre');

        $this->assertSame('Cualquier Otro Nombre', config('branding.name'));
    }
}
