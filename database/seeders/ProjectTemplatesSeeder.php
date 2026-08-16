<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProjectTemplate;
use Illuminate\Database\Seeder;

/**
 * El punto de partida de cada tipo de proyecto que Ariel corre de verdad.
 *
 * Los riesgos no son genéricos a propósito. "Retraso del proveedor" no le sirve
 * a nadie; "el proveedor de importación no libera en aduana a tiempo" sí, porque
 * quien lo lee reconoce el problema y sabe a quién llamar.
 *
 * Esto vive en un seeder y no en un archivo de configuración porque es contenido
 * de negocio: quien lo conoce debe poder corregirlo sin pedir un despliegue.
 */
final class ProjectTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $sortOrder => $template) {
            ProjectTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'is_system' => true,
                    'sort_order' => $sortOrder,
                    'payload' => $template['payload'],
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'systems',
                'name' => 'Sistemas o software',
                'description' => 'Implantación de un sistema, integración, automatización o desarrollo interno.',
                'payload' => [
                    'deliverables' => [
                        'Documento de requerimientos aprobado',
                        'Ambiente de pruebas funcionando',
                        'Sistema en producción',
                        'Usuarios capacitados y manual entregado',
                        'Plan de respaldo probado',
                    ],
                    'stakeholders' => [
                        ['name' => 'Patrocinador del proyecto', 'role_title' => 'Dirección', 'power' => 5, 'interest' => 4],
                        ['name' => 'Usuarios finales del área', 'role_title' => 'Operación', 'power' => 2, 'interest' => 5],
                        ['name' => 'Responsable de TI', 'role_title' => 'Tecnología', 'power' => 4, 'interest' => 4],
                        ['name' => 'Proveedor del sistema', 'role_title' => 'Externo', 'organization' => 'Proveedor', 'power' => 3, 'interest' => 3],
                    ],
                    'risks' => [
                        [
                            'key' => 'sys.requirements_drift',
                            'category' => 'Alcance',
                            'description' => 'Los requerimientos cambian después de arrancar el desarrollo.',
                            'cause' => 'El área no tuvo tiempo de revisar a fondo antes de aprobar.',
                            'effect' => 'Retrabajo y fecha de entrega que se recorre sin control.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'sys.adoption',
                            'category' => 'Personas',
                            'description' => 'La gente sigue usando el método anterior después de la puesta en marcha.',
                            'cause' => 'Capacitación tardía o sistema más lento que el proceso viejo.',
                            'effect' => 'El beneficio esperado no se materializa aunque el sistema funcione.',
                            'probability' => 4, 'impact' => 5, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'sys.it_dependency',
                            'category' => 'Técnico',
                            'description' => 'El servidor no cumple los requisitos técnicos y TI no puede actualizarlo a tiempo.',
                            'cause' => 'Versión de sistema operativo o de motor de base de datos fuera de soporte.',
                            'effect' => 'La entrega se detiene por algo que no depende del equipo del proyecto.',
                            'probability' => 3, 'impact' => 5, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'sys.data_migration',
                            'category' => 'Datos',
                            'description' => 'Los datos históricos vienen sucios o incompletos y la migración los arrastra.',
                            'cause' => 'Capturas manuales de años sin validación.',
                            'effect' => 'El sistema nuevo nace con información en la que nadie confía.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'sys.reuse',
                            'category' => 'Oportunidad',
                            'description' => 'Lo que se construya aquí sirve para otra área sin cambios de fondo.',
                            'effect' => 'El costo se reparte entre dos áreas y el beneficio se duplica.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'opportunity',
                        ],
                    ],
                    'narratives' => [
                        'problem_statement' => 'Hoy el proceso se lleva en hojas de cálculo que cada quien guarda en su equipo. Nadie sabe cuál es la versión buena, y consolidar la información toma dos días al mes.',
                        'expected_benefit' => 'Una sola fuente de información, consultable en el momento, y dos días al mes liberados del trabajo de consolidar.',
                    ],
                ],
            ],
            [
                'key' => 'works',
                'name' => 'Obra o instalación física',
                'description' => 'Adecuación de espacios, instalación de equipo, mantenimiento mayor.',
                'payload' => [
                    'deliverables' => [
                        'Proyecto ejecutivo y presupuesto aprobado',
                        'Permisos y autorizaciones obtenidos',
                        'Obra terminada y recibida',
                        'Pruebas de operación firmadas',
                        'Planos y garantías entregados',
                    ],
                    'stakeholders' => [
                        ['name' => 'Patrocinador del proyecto', 'role_title' => 'Dirección', 'power' => 5, 'interest' => 4],
                        ['name' => 'Contratista', 'role_title' => 'Externo', 'organization' => 'Contratista', 'power' => 3, 'interest' => 5],
                        ['name' => 'Área que ocupa el espacio', 'role_title' => 'Operación', 'power' => 2, 'interest' => 5],
                        ['name' => 'Seguridad e higiene', 'role_title' => 'Cumplimiento', 'power' => 4, 'interest' => 3],
                    ],
                    'risks' => [
                        [
                            'key' => 'works.permits',
                            'category' => 'Regulatorio',
                            'description' => 'El permiso municipal tarda más de lo previsto.',
                            'cause' => 'Los tiempos de la autoridad no dependen del proyecto.',
                            'effect' => 'La obra no puede iniciar y el contratista cobra tiempo de espera.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'works.hidden_conditions',
                            'category' => 'Técnico',
                            'description' => 'Al abrir se encuentra una condición que no estaba en los planos.',
                            'cause' => 'Instalaciones viejas sin documentación confiable.',
                            'effect' => 'Cambio de alcance y de presupuesto a mitad de la obra.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'works.operation_impact',
                            'category' => 'Operación',
                            'description' => 'La obra interrumpe la operación del área más de lo acordado.',
                            'cause' => 'Trabajos ruidosos o cortes de energía en horario laboral.',
                            'effect' => 'Pérdida de producción y presión para acelerar mal la obra.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'works.material_price',
                            'category' => 'Costo',
                            'description' => 'El precio de un material clave sube entre la cotización y la compra.',
                            'effect' => 'El presupuesto aprobado deja de alcanzar.',
                            'probability' => 3, 'impact' => 3, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'works.safety',
                            'category' => 'Seguridad',
                            'description' => 'Un accidente en obra detiene los trabajos.',
                            'cause' => 'Personal externo sin inducción de seguridad del sitio.',
                            'effect' => 'Paro, investigación y responsabilidad para la empresa.',
                            'probability' => 2, 'impact' => 5, 'kind' => 'threat',
                        ],
                    ],
                    'narratives' => [
                        'problem_statement' => 'El espacio actual no permite acomodar el volumen que se opera hoy, y el flujo de materiales se cruza con el de personas.',
                        'expected_benefit' => 'Capacidad para el volumen proyectado y separación de flujos, que es también un requisito de seguridad.',
                    ],
                ],
            ],
            [
                'key' => 'improvement',
                'name' => 'Mejora de proceso',
                'description' => 'Rediseño de un proceso, certificación, reducción de tiempos o de errores.',
                'payload' => [
                    'deliverables' => [
                        'Diagnóstico del proceso actual con datos',
                        'Proceso rediseñado y documentado',
                        'Personal capacitado en el proceso nuevo',
                        'Indicadores en operación',
                    ],
                    'stakeholders' => [
                        ['name' => 'Patrocinador del proyecto', 'role_title' => 'Dirección', 'power' => 5, 'interest' => 4],
                        ['name' => 'Dueño del proceso', 'role_title' => 'Gerencia del área', 'power' => 4, 'interest' => 5],
                        ['name' => 'Quienes ejecutan el proceso', 'role_title' => 'Operación', 'power' => 2, 'interest' => 5],
                        ['name' => 'Cliente del proceso', 'role_title' => 'Área siguiente', 'power' => 3, 'interest' => 4],
                    ],
                    'risks' => [
                        [
                            'key' => 'imp.no_baseline',
                            'category' => 'Medición',
                            'description' => 'No hay datos del proceso actual, así que no se podrá demostrar la mejora.',
                            'cause' => 'Nunca se midió el tiempo ni los errores del proceso viejo.',
                            'effect' => 'Al final nadie puede decir si mejoró, y el proyecto se percibe como gasto.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'imp.resistance',
                            'category' => 'Personas',
                            'description' => 'Quienes ejecutan el proceso lo ven como una amenaza a su puesto.',
                            'effect' => 'Información incompleta durante el diagnóstico y regreso al método viejo.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'imp.local_optimum',
                            'category' => 'Alcance',
                            'description' => 'Se optimiza un tramo y el cuello de botella se mueve al área siguiente.',
                            'effect' => 'El proceso completo no mejora, y el área vecina paga el costo.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'imp.standardize',
                            'category' => 'Oportunidad',
                            'description' => 'El proceso rediseñado puede estandarizarse en las demás sucursales.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'opportunity',
                        ],
                    ],
                    'narratives' => [
                        'problem_statement' => 'El proceso toma más tiempo del que debería y depende de que una persona en particular esté disponible. Cuando falta, se detiene.',
                        'expected_benefit' => 'Un proceso que cualquiera del área puede ejecutar, con un tiempo estándar medible.',
                    ],
                ],
            ],
            [
                'key' => 'launch',
                'name' => 'Lanzamiento de producto o servicio',
                'description' => 'Producto nuevo, línea nueva, entrada a un mercado o a un canal.',
                'payload' => [
                    'deliverables' => [
                        'Definición de producto y precio aprobada',
                        'Inventario inicial disponible',
                        'Material comercial y capacitación de ventas',
                        'Lanzamiento ejecutado',
                        'Primer corte de resultados',
                    ],
                    'stakeholders' => [
                        ['name' => 'Patrocinador del proyecto', 'role_title' => 'Dirección', 'power' => 5, 'interest' => 5],
                        ['name' => 'Fuerza de ventas', 'role_title' => 'Comercial', 'power' => 3, 'interest' => 5],
                        ['name' => 'Compras e importación', 'role_title' => 'Abasto', 'power' => 4, 'interest' => 3],
                        ['name' => 'Clientes clave', 'role_title' => 'Externo', 'organization' => 'Cliente', 'power' => 4, 'interest' => 4],
                    ],
                    'risks' => [
                        [
                            'key' => 'launch.customs',
                            'category' => 'Abasto',
                            'description' => 'El producto no libera en aduana a tiempo para la fecha de lanzamiento.',
                            'cause' => 'Documentación de importación incompleta o clasificación arancelaria en revisión.',
                            'effect' => 'Se anuncia un producto que no se puede surtir.',
                            'probability' => 3, 'impact' => 5, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'launch.demand',
                            'category' => 'Mercado',
                            'description' => 'La demanda real resulta muy distinta a la estimada.',
                            'effect' => 'Inventario detenido, o desabasto en las primeras semanas.',
                            'probability' => 4, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'launch.sales_readiness',
                            'category' => 'Comercial',
                            'description' => 'La fuerza de ventas no sabe explicar el producto el día del lanzamiento.',
                            'cause' => 'Capacitación programada demasiado cerca de la fecha.',
                            'effect' => 'Primeras semanas perdidas, que son las que marcan la percepción.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'launch.exchange_rate',
                            'category' => 'Costo',
                            'description' => 'El tipo de cambio se mueve entre la compra y la venta.',
                            'effect' => 'El margen planeado se reduce o desaparece.',
                            'probability' => 4, 'impact' => 3, 'kind' => 'threat',
                        ],
                        [
                            'key' => 'launch.cross_sell',
                            'category' => 'Oportunidad',
                            'description' => 'El producto abre la puerta con clientes que hoy compran solo una línea.',
                            'probability' => 3, 'impact' => 4, 'kind' => 'opportunity',
                        ],
                    ],
                    'narratives' => [
                        'problem_statement' => 'Los clientes piden un producto que hoy no se maneja y lo están comprando con otro proveedor, junto con líneas que sí se manejan.',
                        'expected_benefit' => 'Retener la compra completa del cliente y abrir una línea con margen propio.',
                    ],
                ],
            ],
        ];
    }
}
