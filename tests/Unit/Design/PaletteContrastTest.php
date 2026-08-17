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

    /**
     * Las dos paletas: la clara y la oscura.
     *
     * Se revisan **las dos** porque el tema es elegible. Antes solo se verificaba
     * la que estaba activa, y eso deja la mitad del sistema sin comprobar: un
     * color que se lee perfecto en oscuro puede ser ilegible en claro, y quien
     * eligio claro no tiene por que descubrirlo.
     *
     * Los nombres son los del papel de cada color, no los del token: lo que se
     * verifica es <<el texto secundario sobre una tarjeta>>, no <<slate-600>>.
     *
     * @var array<string, array<string, string>>
     */
    private const PALETTES = [
        'oscuro' => [
            'canvas' => '#070b14',
            'surface' => '#0e1526',
            'surface-raised' => '#131c30',
            'shell' => '#060a12',
            'ink' => '#eef3fb',
            'muted' => '#a8b6d1',
            'dim' => '#8f9fbd',
            'hud-400' => '#22d3ee',
            'hud-500' => '#06b6d4',
            'hud-600' => '#22d3ee',
            'hud-ink' => '#04141a',
            'brand-700' => '#7cbcfd',
            'brand-800' => '#a8d3fe',
            'nav-active' => '#10233f',
            'nav-text' => '#a8b6d1',
            'ok-bg' => '#06281f',
            'ok-text' => '#6ee7b7',
            'warn-bg' => '#2c1f05',
            'warn-text' => '#fcd34d',
            'danger-bg' => '#2e0f18',
            'danger-text' => '#fda4af',
            'neutral-bg' => '#1b2740',
            'neutral-text' => '#c3cee2',
            'bar' => '#38bdf8',
            'bar-critical' => '#fb7185',
            'fill-brand' => '#38bdf8',
            'fill-brand-ink' => '#04141a',
            'fill-danger' => '#fb7185',
            'fill-danger-ink' => '#2e0f18',
            'fill-ok' => '#34d399',
            'fill-ok-ink' => '#04231a',
            'fill-warn' => '#fbbf24',
            'fill-warn-ink' => '#2c1f05',
            'white' => '#ffffff',
        ],
        'claro' => [
            'canvas' => '#eef2f7',
            'surface' => '#ffffff',
            'surface-raised' => '#f8fafc',
            'shell' => '#0b1220',
            'ink' => '#0f172a',
            'muted' => '#475569',
            'dim' => '#64748b',
            'hud-400' => '#0891b2',
            'hud-500' => '#0e7490',
            'hud-600' => '#155e75',
            'hud-ink' => '#ffffff',
            'brand-700' => '#1e40af',
            'brand-800' => '#1e3a8a',
            'nav-active' => '#10233f',
            'nav-text' => '#a8b6d1',
            'ok-bg' => '#ecfdf5',
            'ok-text' => '#065f46',
            'warn-bg' => '#fffbeb',
            'warn-text' => '#92400e',
            'danger-bg' => '#fef2f2',
            'danger-text' => '#991b1b',
            'neutral-bg' => '#f1f5f9',
            'neutral-text' => '#334155',
            'bar' => '#1d4ed8',
            'bar-critical' => '#dc2626',
            'fill-brand' => '#2563eb',
            'fill-brand-ink' => '#ffffff',
            'fill-danger' => '#dc2626',
            'fill-danger-ink' => '#ffffff',
            'fill-ok' => '#047857',
            'fill-ok-ink' => '#ffffff',
            'fill-warn' => '#b45309',
            'fill-warn-ink' => '#ffffff',
            'white' => '#ffffff',
        ],
    ];

    /**
     * @return array<string, array{string, string, float, string}>
     */
    public static function combinations(): array
    {
        return [
            // --- Texto sobre las tres superficies -------------------------
            'texto principal sobre tarjeta' => ['ink', 'surface', self::NORMAL_TEXT, 'El texto de todas las pantallas.'],
            'texto principal sobre lienzo' => ['ink', 'canvas', self::NORMAL_TEXT, 'El fondo de la aplicación.'],
            'texto secundario sobre tarjeta' => ['muted', 'surface', self::NORMAL_TEXT, 'Las ayudas bajo cada campo.'],
            'texto secundario sobre lienzo' => ['muted', 'canvas', self::NORMAL_TEXT, 'Los pies de sección.'],
            'texto secundario sobre panel elevado' => ['muted', 'surface-raised', self::NORMAL_TEXT, 'El encabezado de las tablas.'],
            'texto tenue sobre tarjeta' => ['dim', 'surface', self::NORMAL_TEXT, 'Las etiquetas de los indicadores.'],

            // --- Menú lateral ---------------------------------------------
            'menu lateral' => ['nav-text', 'shell', self::NORMAL_TEXT, 'La navegación completa. El menú es oscuro en los dos temas a propósito, y fija su propio color.'],
            'menu lateral activo' => ['white', 'nav-active', self::NORMAL_TEXT, 'La sección en la que estás.'],

            // --- Botones --------------------------------------------------
            'boton primario' => ['hud-ink', 'hud-500', self::NORMAL_TEXT, 'Guardar, crear, aplicar.'],
            'boton primario al pasar encima' => ['hud-ink', 'hud-600', self::NORMAL_TEXT, 'El mismo botón con el cursor. En claro se oscurece; en oscuro se aclara.'],
            'boton secundario' => ['neutral-text', 'surface-raised', self::NORMAL_TEXT, 'Cancelar, ver, imprimir.'],

            // --- Distintivos de estado -----------------------------------
            'distintivo neutro' => ['neutral-text', 'neutral-bg', self::NORMAL_TEXT, 'Estado sin alarma.'],
            'distintivo correcto' => ['ok-text', 'ok-bg', self::NORMAL_TEXT, 'Terminado, vigente.'],
            'distintivo de aviso' => ['warn-text', 'warn-bg', self::NORMAL_TEXT, 'Conviene revisar.'],
            'distintivo de peligro' => ['danger-text', 'danger-bg', self::NORMAL_TEXT, 'Amenaza la entrega.'],

            // --- Lo crítico -----------------------------------------------
            'ruta critica sobre tarjeta' => ['bar-critical', 'surface', self::NORMAL_TEXT, 'La holgura negativa y la ruta crítica.'],
            'enlace sobre tarjeta' => ['brand-700', 'surface', self::NORMAL_TEXT, 'Todos los enlaces del sistema.'],
            'acento como texto' => ['hud-500', 'surface', self::NORMAL_TEXT, 'Los números vivos y los acentos.'],
            'anillo de foco' => ['hud-400', 'surface', self::LARGE_TEXT, 'El anillo de foco y el filo de las tarjetas.'],

            // --- Barras y elementos gráficos (umbral de interfaz) ---------
            'barra del Gantt' => ['bar', 'surface', self::LARGE_TEXT, 'Las barras del diagrama.'],
            'barra critica del Gantt' => ['bar-critical', 'surface', self::LARGE_TEXT, 'Las barras de la ruta crítica.'],

            // --- Rellenos sólidos: el fondo **y su tinta** -----------------
            //
            // Es la combinación que estaba rota: el calendario pintaba blanco
            // sobre `brand-600`, que en oscuro es azul claro. Aquí el fondo y su
            // tinta viajan en pareja y se comprueban juntos en los dos temas.
            'chip de tarea' => ['fill-brand-ink', 'fill-brand', self::NORMAL_TEXT, 'Las tareas en la vista de calendario.'],
            'chip de tarea critica' => ['fill-danger-ink', 'fill-danger', self::NORMAL_TEXT, 'Las tareas críticas en el calendario.'],
            'relleno correcto' => ['fill-ok-ink', 'fill-ok', self::NORMAL_TEXT, 'Barras y sellos de conformidad.'],
            'relleno de aviso' => ['fill-warn-ink', 'fill-warn', self::NORMAL_TEXT, 'Barras y sellos de atención.'],

            // Las mismas, como barra sin texto: umbral de interfaz.
            'barra de carga' => ['fill-brand', 'surface', self::LARGE_TEXT, 'El histograma de carga.'],
            'barra sobrecargada' => ['fill-danger', 'surface', self::LARGE_TEXT, 'La carga por encima de la capacidad.'],
        ];
    }

    #[Test]
    #[DataProvider('combinations')]
    public function the_combination_meets_the_threshold_in_both_themes(string $foreground, string $background, float $threshold, string $where): void
    {
        foreach (self::PALETTES as $theme => $palette) {
            $ratio = $this->contrast($palette[$foreground], $palette[$background]);

            $this->assertGreaterThanOrEqual(
                $threshold,
                $ratio,
                sprintf(
                    'En tema %s, %s sobre %s da %.2f:1 y necesita %.1f:1. Dónde se usa: %s',
                    $theme,
                    $foreground,
                    $background,
                    $ratio,
                    $threshold,
                    $where,
                ),
            );
        }
    }

    /**
     * Los dos colores que distinguen la ruta crítica del resto no pueden
     * diferenciarse solo por tono: quien no distingue rojo de azul necesita que
     * también cambie la luminosidad.
     */
    #[Test]
    public function critical_and_normal_bars_differ_in_more_than_hue(): void
    {
        foreach (self::PALETTES as $theme => $palette) {
            $normal = $this->relativeLuminance($palette['bar']);
            $critical = $this->relativeLuminance($palette['bar-critical']);

            $this->assertNotEqualsWithDelta(
                $normal,
                $critical,
                0.01,
                "En tema {$theme}, la barra crítica y la normal deben separarse también en luminosidad, no solo en color.",
            );
        }
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
