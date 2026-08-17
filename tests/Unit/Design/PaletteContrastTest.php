<?php

declare(strict_types=1);

namespace Tests\Unit\Design;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El contraste de la paleta, verificado en cada revisión.
 *
 * Se comprueba aquí y no con una herramienta corrida una vez porque la paleta
 * cambia: alguien aclara un gris para que «se vea más suave» y de paso deja
 * ilegible la mitad de la aplicación para quien tiene poca visión. Una hoja de
 * cálculo con los resultados de hace tres meses no lo detiene; esta prueba sí.
 *
 * Umbrales de WCAG 2.1 AA:
 *  - Texto normal: 4.5:1
 *  - Texto grande (18.5px+ o 14px+ en negrita): 3:1
 *  - Elementos de interfaz y bordes: 3:1
 */
final class PaletteContrastTest extends TestCase
{
    private const NORMAL_TEXT = 4.5;

    private const LARGE_TEXT = 3.0;

    /** @var array<string, string> */
    private const PALETTE = [
        'canvas' => '#f1f5f9',
        'surface' => '#ffffff',
        'ink' => '#0f172a',
        'muted' => '#475569',
        'brand-700' => '#1e40af',
        'brand-800' => '#1e3a8a',
        'brand-50' => '#eff5ff',
        'sidebar' => '#0f172a',
        'sidebar-text' => '#cbd5e1',
        'danger-700' => '#b91c1c',
        'danger-50' => '#fef2f2',
        'danger-900' => '#991b1b',
        'ok-50' => '#ecfdf5',
        'ok-800' => '#065f46',
        'warn-50' => '#fffbeb',
        'warn-900' => '#92400e',
        'neutral-50' => '#f1f5f9',
        'neutral-700' => '#334155',
        'white' => '#ffffff',
    ];

    /**
     * @return array<string, array{string, string, float, string}>
     */
    public static function combinations(): array
    {
        return [
            // --- Texto sobre las dos superficies -------------------------
            'texto principal sobre tarjeta' => ['ink', 'surface', self::NORMAL_TEXT, 'El texto de todas las pantallas.'],
            'texto principal sobre lienzo' => ['ink', 'canvas', self::NORMAL_TEXT, 'El fondo de la aplicación.'],
            'texto secundario sobre tarjeta' => ['muted', 'surface', self::NORMAL_TEXT, 'Las ayudas bajo cada campo.'],
            'texto secundario sobre lienzo' => ['muted', 'canvas', self::NORMAL_TEXT, 'Los pies de sección.'],

            // --- Menú lateral oscuro -------------------------------------
            'menu lateral' => ['sidebar-text', 'sidebar', self::NORMAL_TEXT, 'La navegación completa.'],
            'menu lateral activo' => ['white', 'brand-700', self::NORMAL_TEXT, 'La sección en la que estás.'],

            // --- Botones --------------------------------------------------
            'boton primario' => ['white', 'brand-700', self::NORMAL_TEXT, 'Guardar, crear, aplicar.'],
            'boton primario al pasar encima' => ['white', 'brand-800', self::NORMAL_TEXT, 'El mismo botón con el cursor.'],
            'boton secundario' => ['neutral-700', 'surface', self::NORMAL_TEXT, 'Cancelar, ver, imprimir.'],

            // --- Distintivos de estado -----------------------------------
            'distintivo neutro' => ['neutral-700', 'neutral-50', self::NORMAL_TEXT, 'Estado sin alarma.'],
            'distintivo correcto' => ['ok-800', 'ok-50', self::NORMAL_TEXT, 'Terminado, vigente.'],
            'distintivo de aviso' => ['warn-900', 'warn-50', self::NORMAL_TEXT, 'Conviene revisar.'],
            'distintivo de peligro' => ['danger-900', 'danger-50', self::NORMAL_TEXT, 'Amenaza la entrega.'],

            // --- Lo crítico -----------------------------------------------
            'ruta critica sobre tarjeta' => ['danger-700', 'surface', self::NORMAL_TEXT, 'La holgura negativa y la ruta crítica.'],
            'enlace sobre tarjeta' => ['brand-700', 'surface', self::NORMAL_TEXT, 'Todos los enlaces del sistema.'],
            'renglon resaltado' => ['ink', 'brand-50', self::NORMAL_TEXT, 'La fila bajo el cursor en las tablas.'],

            // --- Barras y elementos gráficos (umbral de interfaz) ---------
            'barra del Gantt' => ['brand-700', 'surface', self::LARGE_TEXT, 'Las barras del diagrama.'],
            'barra critica del Gantt' => ['danger-700', 'surface', self::LARGE_TEXT, 'Las barras de la ruta crítica.'],
        ];
    }

    #[Test]
    #[DataProvider('combinations')]
    public function the_combination_meets_the_threshold(string $foreground, string $background, float $threshold, string $where): void
    {
        $ratio = $this->contrast(self::PALETTE[$foreground], self::PALETTE[$background]);

        $this->assertGreaterThanOrEqual(
            $threshold,
            $ratio,
            sprintf(
                '%s sobre %s da %.2f:1 y necesita %.1f:1. Dónde se usa: %s',
                $foreground,
                $background,
                $ratio,
                $threshold,
                $where,
            ),
        );
    }

    /**
     * Los dos colores que distinguen la ruta crítica del resto no pueden
     * diferenciarse solo por tono: quien no distingue rojo de azul necesita que
     * también cambie la luminosidad.
     */
    #[Test]
    public function critical_and_normal_bars_differ_in_more_than_hue(): void
    {
        $normal = $this->relativeLuminance(self::PALETTE['brand-700']);
        $critical = $this->relativeLuminance(self::PALETTE['danger-700']);

        $this->assertNotEqualsWithDelta(
            $normal,
            $critical,
            0.01,
            'La barra crítica y la normal deben separarse también en luminosidad, no solo en color.',
        );
    }

    /** Fórmula de WCAG 2.1: (L1 + 0.05) / (L2 + 0.05). */
    private function contrast(string $a, string $b): float
    {
        $first = $this->relativeLuminance($a);
        $second = $this->relativeLuminance($b);

        [$lighter, $darker] = $first > $second ? [$first, $second] : [$second, $first];

        return round(($lighter + 0.05) / ($darker + 0.05), 3);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = array_map(
            function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
