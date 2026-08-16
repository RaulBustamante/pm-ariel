<?php

declare(strict_types=1);

/**
 * Glosario contextual. Cada término lleva `_label`, la definición en una línea
 * y, cuando ayuda, un ejemplo de Ariel.
 *
 * La regla al escribir aquí: si la definición usa otro término del glosario,
 * está mal escrita. Estas frases son para quien nunca ha llevado un proyecto
 * formal, no para quien ya sabe.
 */
return [

    'what_is' => '¿Qué es :term?',

    'charter_label' => 'Acta constitutiva',
    'charter' => 'El documento de una o dos hojas que autoriza el proyecto: por qué existe, qué entrega, quién lo dirige y con qué límites.',
    'charter_example' => 'Es lo que se firma antes de gastar el primer peso. Sin acta, cada quien recuerda un acuerdo distinto.',

    'stakeholder_label' => 'Interesado',
    'stakeholder' => 'Cualquiera a quien el proyecto le afecte o que pueda afectarlo, dentro o fuera de la empresa.',
    'stakeholder_example' => 'El gerente que autoriza el gasto, el equipo que va a usar el sistema, y el proveedor que entrega el equipo.',

    'power_label' => 'Poder',
    'power' => 'Qué tanto puede esta persona decidir, detener o destrabar el proyecto.',
    'power_example' => 'Quien firma el presupuesto tiene poder alto aunque nunca use lo que se construya.',

    'interest_label' => 'Interés',
    'interest' => 'Qué tanto le cambia el día a día el resultado del proyecto.',
    'interest_example' => 'A quien va a usar el sistema todos los días le interesa mucho, aunque no decida nada.',

    'sponsor_label' => 'Patrocinador',
    'sponsor' => 'El directivo que autoriza el presupuesto y destraba lo que el equipo no puede resolver solo.',
    'sponsor_example' => 'Cuando dos áreas no se ponen de acuerdo, es quien decide. Sin él, el proyecto se detiene ahí.',

    'objective_label' => 'Objetivo',
    'objective' => 'Lo que se quiere lograr, no lo que se va a hacer.',
    'objective_example' => '«Reducir a un día el cierre mensual» es un objetivo. «Programar un sistema» es una actividad.',

    'deliverable_label' => 'Entregable',
    'deliverable' => 'Algo concreto que se puede revisar y aprobar cuando esté listo.',
    'deliverable_example' => '«Manual entregado y firmado» es un entregable. «Capacitar al personal» no lo es: no se puede revisar.',

    'success_criteria_label' => 'Criterio de éxito',
    'success_criteria' => 'La prueba concreta que dirá si el proyecto cumplió, acordada antes de empezar.',
    'success_criteria_example' => '«El cierre de marzo se hace en un día con cero diferencias.» Eso se puede verificar; «que quede bien» no.',

    'assumption_label' => 'Supuesto',
    'assumption' => 'Algo que estás dando por cierto sin haberlo confirmado.',
    'assumption_example' => '«TI nos dará el servidor en enero.» Si falla, se vuelve un problema — por eso se escribe desde ahora.',

    'constraint_label' => 'Restricción',
    'constraint' => 'Un límite que no se puede mover: fecha, dinero, gente o una regla.',
    'constraint_example' => '«Tiene que estar antes de la auditoría de mayo» es una restricción, no un objetivo.',

    'out_of_scope_label' => 'Fuera de alcance',
    'out_of_scope' => 'Lo que el proyecto NO va a hacer, escrito a propósito.',
    'out_of_scope_example' => 'Es el campo que evita la discusión de «yo pensé que también incluía…» tres meses después.',

    'risk_label' => 'Riesgo',
    'risk' => 'Algo que todavía no pasa y que, si pasa, cambia el resultado del proyecto.',
    'risk_example' => 'Si ya pasó, no es un riesgo: es un problema, y se atiende de otra manera.',

    'probability_label' => 'Probabilidad',
    'probability' => 'Qué tan posible es que ese riesgo ocurra, del 1 al 5.',

    'impact_label' => 'Impacto',
    'impact' => 'Qué tanto dolería si ocurriera, del 1 al 5.',

    'risk_owner_label' => 'Responsable del riesgo',
    'risk_owner' => 'La persona que lo vigila y avisa si empieza a acercarse.',
    'risk_owner_example' => 'No es quien lo resuelve necesariamente: es quien se da cuenta a tiempo.',

    'risk_response_label' => 'Respuesta al riesgo',
    'risk_response' => 'Qué se va a hacer concretamente con ese riesgo, decidido antes de que ocurra.',
    'risk_response_example' => 'Hay cinco caminos: evitarlo, reducirlo, pasarlo a un tercero, aceptarlo, o escalarlo a quien sí puede.',

    'opportunity_label' => 'Oportunidad',
    'opportunity' => 'Un riesgo bueno: algo que podría pasar y mejoraría el resultado si se aprovecha.',
    'opportunity_example' => '«Si el proveedor entrega antes, podemos lanzar en temporada alta.» No aprovecharla también cuesta.',

    'justification_label' => 'Justificación',
    'justification' => 'La razón por la que vale la pena hacer este proyecto y no otro.',

];
