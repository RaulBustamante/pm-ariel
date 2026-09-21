<?php

declare(strict_types=1);

return [

    // Ya no van numerados. El alta dejó de ser cuatro pasos en fila: lo
    // esencial está a la vista y esto son los grupos de «Más opciones», que se
    // llenan en cualquier orden o no se llenan. Un «3 ·» ahí prometería un
    // recorrido que no existe.
    'step_what' => 'Qué',
    'step_who' => 'Quién',
    'step_when' => 'Cuándo',
    'step_measure' => 'Cómo se mide',

    'step_who_help' => 'Quien crea el proyecto queda como gerente. Los que marques aquí podrán editar el plan; los demás solo verán lo que su jefatura les permita.',
    'step_when_help' => 'Desde cuándo se puede trabajar. Todas las fechas del plan se calculan a partir de aquí.',

    // El plegado. El resumen promete que no hace falta abrirlo, porque si la
    // gente siente que se está saltando algo, lo abre por miedo y volvemos al
    // muro de campos que nos pidieron quitar.
    'more_options' => 'Más opciones',
    'more_options_help' => 'Nada de aquí es obligatorio y todo se puede cambiar después. Ábrelo solo si ya tienes el equipo o las fechas.',
    'start_defaults_today' => 'Si lo dejas vacío, el proyecto arranca hoy. Todas las fechas del plan se calculan a partir de aquí.',

    'deliverables_placeholder' => "Documento de requerimientos aprobado\nAmbiente de pruebas funcionando\nSistema en producción",
    'deliverables_become_tasks' => 'Cada renglón se convierte en una tarea de primer nivel del plan. Así terminas el asistente con un plan, no con una pantalla vacía.',

];
