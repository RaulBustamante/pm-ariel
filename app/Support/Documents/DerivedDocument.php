<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Project;

/**
 * El motor de los documentos que se generan solos. La quinta maquinaria, y la
 * que cierra el catálogo.
 *
 * **Por qué faltaba.** D-022 clasificó los setenta documentos en cuatro
 * especies y se construyó un motor para tres de ellas. La cuarta —`derived`— se
 * fue resolviendo uno por uno: cada documento se enrutaba a la pantalla que ya
 * tenía sus datos, el cronograma al Gantt, los costos al análisis. Funcionó
 * mientras hubo pantalla a la que apuntar, y dejó fuera exactamente a los que no
 * la tenían. Los últimos doce documentos del catálogo son todos de esta especie,
 * y ninguno tiene dónde vivir.
 *
 * Un documento derivado siempre es lo mismo: **una consulta que devuelve
 * renglones y una tabla que los pinta**. Lo que cambia entre el diccionario de
 * la EDT y el informe de riesgos es de dónde salen los renglones y cómo se
 * llaman las columnas — y eso cabe en `config/pmi_derived.php`.
 *
 * Nada se captura aquí. Si un documento derivado saliera vacío, la respuesta no
 * es un formulario: es que falta capturar el dato en su pantalla, y esta lo
 * dice en vez de ofrecer teclearlo por segunda vez en otro lado.
 */
final class DerivedDocument
{
    public function __construct(
        private readonly DerivedSources $sources,
    ) {}

    /**
     * ¿Este código es un derivado que este motor sabe armar?
     *
     * Se piden las dos cosas, como en los otros motores: hay derivados que
     * viven en su propia pantalla desde antes —el cronograma, el corte semanal—
     * y esos no pasan por aquí.
     */
    public function handles(string $code): bool
    {
        return (string) config("pmi_documents.catalogue.{$code}.kind") === 'derived'
            && is_array(config("pmi_derived.documents.{$code}"));
    }

    /**
     * @return list<string>
     */
    public function columns(string $code): array
    {
        /** @var list<string> $columns */
        $columns = config("pmi_derived.documents.{$code}.columns", []);

        return $columns;
    }

    /** ¿Esta columna se alinea a la derecha y con cifras tabulares? */
    public function isNumeric(string $code, string $column): bool
    {
        /** @var list<string> $numeric */
        $numeric = config("pmi_derived.documents.{$code}.numeric", []);

        return in_array($column, $numeric, strict: true);
    }

    public function isLandscape(string $code): bool
    {
        return (bool) config("pmi_derived.documents.{$code}.landscape", false);
    }

    /**
     * Los renglones del documento.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(Project $project, string $code): array
    {
        $source = (string) config("pmi_derived.documents.{$code}.source", '');

        return $this->sources->rowsFor($source, $project);
    }
}
