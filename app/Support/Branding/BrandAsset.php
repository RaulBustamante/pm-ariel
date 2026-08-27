<?php

declare(strict_types=1);

namespace App\Support\Branding;

/**
 * La URL de un archivo de marca, con la huella de su contenido pegada.
 *
 * Existe por una falla concreta y cara de diagnosticar: **el nombre del archivo
 * no cambia cuando cambia el logotipo**, y no debe cambiar, porque
 * `BRANDING_LOGO` lo fija en el `.env` de cada entorno. Sin esto, el servidor
 * queda con el archivo nuevo, el despliegue reporta todo en verde, y el
 * navegador sigue pintando el que ya tenía guardado.
 *
 * Esa es la peor combinación posible: no hay error que buscar, no hay registro
 * que revisar, y la pantalla dice que el cambio no se hizo. Se pierde la tarde
 * revisando el servidor, que está bien.
 *
 * Con la huella, la dirección cambia sola en cuanto cambia un byte del archivo,
 * y el navegador no tiene de dónde agarrar la versión vieja. Nadie tiene que
 * acordarse de nada.
 */
final class BrandAsset
{
    /**
     * Las huellas ya calculadas de esta petición.
     *
     * Los archivos de marca aparecen hasta tres veces en una sola pantalla —el
     * icono, el de la barra y el del panel—, y leer el archivo tres veces para
     * obtener el mismo resultado no tiene sentido.
     *
     * @var array<string, string>
     */
    private static array $resolved = [];

    /** `logo` o `mark`, las llaves de `config/branding.php`. */
    public static function url(string $key): string
    {
        $path = (string) config("branding.{$key}", '');

        if ($path === '') {
            return '';
        }

        return self::$resolved[$path] ??= self::stamped($path);
    }

    private static function stamped(string $path): string
    {
        $url = asset($path);
        $file = public_path($path);

        // Un archivo que no está se devuelve tal cual, sin huella. Inventar una
        // no arreglaría nada y sí escondería el problema: la imagen rota es la
        // señal de que la marca apunta a donde no hay nada.
        if (! is_file($file)) {
            return $url;
        }

        $stamp = substr((string) md5_file($file), 0, 8);

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$stamp;
    }

    /** Para las pruebas: la memoria de una petición no debe cruzarse con otra. */
    public static function forget(): void
    {
        self::$resolved = [];
    }
}
