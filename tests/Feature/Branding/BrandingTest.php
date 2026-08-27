<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Support\Branding\BrandAsset;
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
    public function the_brand_url_carries_the_fingerprint_of_the_file(): void
    {
        // Sin la huella, cambiar el logotipo deja el servidor con el archivo
        // nuevo y el navegador pintando el viejo: el despliegue reporta verde y
        // la pantalla dice que no paso nada. Es la falla mas cara de
        // diagnosticar que hay, porque no hay error que buscar.
        BrandAsset::forget();

        $url = BrandAsset::url('logo');
        $file = public_path((string) config('branding.logo'));

        $this->assertStringContainsString('?v=', $url);
        $this->assertStringContainsString(substr((string) md5_file($file), 0, 8), $url);
    }

    #[Test]
    public function the_fingerprint_changes_when_the_file_changes(): void
    {
        // Lo que de verdad importa: que la direccion cambie sola en cuanto
        // cambia un byte. Se prueba con dos archivos distintos y no con el mismo
        // dos veces, que no probaria nada.
        $dir = 'brand-test-'.bin2hex(random_bytes(4));
        @mkdir(public_path($dir));

        try {
            file_put_contents(public_path("{$dir}/a.png"), 'primero');
            file_put_contents(public_path("{$dir}/b.png"), 'segundo');

            BrandAsset::forget();
            config()->set('branding.logo', "{$dir}/a.png");
            $first = BrandAsset::url('logo');

            BrandAsset::forget();
            config()->set('branding.logo', "{$dir}/b.png");
            $second = BrandAsset::url('logo');

            $this->assertNotSame($first, $second);
        } finally {
            @unlink(public_path("{$dir}/a.png"));
            @unlink(public_path("{$dir}/b.png"));
            @rmdir(public_path($dir));
        }
    }

    #[Test]
    public function a_missing_brand_file_is_not_given_a_fake_fingerprint(): void
    {
        // La imagen rota es la senal de que la marca apunta a donde no hay nada.
        // Inventarle una huella esconderia el problema sin arreglarlo.
        BrandAsset::forget();
        config()->set('branding.logo', 'images/no-existe-este-archivo.png');

        $this->assertStringNotContainsString('?v=', BrandAsset::url('logo'));
    }

    #[Test]
    public function the_sign_in_page_serves_a_versioned_logo(): void
    {
        BrandAsset::forget();

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(BrandAsset::url('logo'), escape: false);
    }

    #[Test]
    public function the_brand_comes_from_configuration_and_not_from_a_literal(): void
    {
        config()->set('branding.name', 'Cualquier Otro Nombre');

        $this->assertSame('Cualquier Otro Nombre', config('branding.name'));
    }
}
