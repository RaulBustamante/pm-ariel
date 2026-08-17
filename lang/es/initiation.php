<?php

declare(strict_types=1);

return [

    'title' => 'Inicio del proyecto',
    'heading' => 'Arrancar bien este proyecto',
    'intro' => 'Cuatro pasos. Al final tienes el acta constitutiva, quiénes son los interesados y qué puede salir mal — que es todo lo que se necesita para pedir un sí con fundamento.',

    'new_project' => 'Nuevo proyecto',
    'start' => 'Empezar',
    'continue' => 'Continuar donde me quedé',
    'save_and_continue' => 'Guardar y continuar',
    'save_and_exit' => 'Guardar y salir',
    'back' => 'Anterior',
    'step_of' => 'Paso :current de :total',

    'projects' => 'Proyectos',
    'no_projects' => 'Todavía no hay proyectos. El primero se crea con el recorrido guiado, que toma unos veinte minutos y deja los documentos listos.',

    // --- Los pasos ---

    'step_justification_title' => 'Por qué',
    'step_justification_purpose' => 'Para qué existe este proyecto y qué gana la empresa si sale bien.',

    'step_stakeholders_title' => 'A quién le importa',
    'step_stakeholders_purpose' => 'Quiénes pueden ayudar o estorbar, y cómo tratar a cada uno.',

    'step_charter_title' => 'Qué entrega',
    'step_charter_purpose' => 'Los objetivos, los entregables y cómo se sabrá que salió bien.',

    'step_risks_title' => 'Qué puede salir mal',
    'step_risks_purpose' => 'Lo que todavía no pasa pero cambiaría el resultado, y qué se hará al respecto.',

    // --- Alta del proyecto ---

    'project_name' => 'Nombre del proyecto',
    'project_name_help' => 'Como lo llamaría alguien en un pasillo. "Sistema de inventario de refacciones", no "Proyecto 2026-04".',
    'project_code' => 'Clave',
    'project_code_help' => 'Corta y única. Sirve para citarlo en correos y reportes.',
    'project_description' => 'De qué se trata, en dos líneas',
    'project_type' => 'Tipo de proyecto',
    'project_type_help' => 'Precarga los riesgos e interesados que casi siempre aparecen en ese tipo. Todo se puede borrar y cambiar.',
    'no_template' => 'Empezar de cero, sin precargar nada',
    'created' => 'Proyecto creado. Empecemos por el porqué.',

    // --- Paso 1: justificación ---

    'field_problem_statement' => 'Cuál es el problema hoy',
    'help_problem_statement' => 'Qué duele ahora mismo, sin proponer todavía la solución. Si no duele nada, tal vez el proyecto no hace falta.',
    'example_problem_statement' => 'Ejemplo: «Cada cierre de mes tomamos dos días en consolidar cinco hojas de cálculo distintas, y en tres de los últimos seis meses hubo diferencias que hubo que rastrear a mano.»',

    'field_opportunity' => 'Qué oportunidad se abre',
    'help_opportunity' => 'Opcional. A veces no hay un problema sino algo que se puede ganar.',

    'field_expected_benefit' => 'Qué se gana si sale bien',
    'help_expected_benefit' => 'Lo más concreto posible: horas, pesos, errores evitados, clientes retenidos. Un beneficio que no se puede notar tampoco se puede defender.',
    'example_expected_benefit' => 'Ejemplo: «Dos días de trabajo liberados cada mes y cero diferencias que rastrear.»',

    'field_alignment' => 'Con qué objetivo de la empresa se alinea',
    'help_alignment' => 'Por qué esto y no otra cosa. Es lo que dirección va a preguntar.',

    // --- Paso 2: interesados ---

    'stakeholders_intro' => 'Un interesado es cualquiera a quien el proyecto le afecte o que pueda afectarlo — de dentro o de fuera. Ubícalo en la matriz y el sistema propone cómo tratarlo.',
    'add_stakeholder' => 'Agregar interesado',
    'edit_stakeholder' => 'Editar interesado',
    'stakeholder_name' => 'Nombre o puesto',
    'stakeholder_name_help' => 'Si todavía no sabes quién es la persona, pon el puesto: «Gerente de Compras».',
    'stakeholder_organization' => 'Organización',
    'stakeholder_role' => 'Papel',
    'stakeholder_email' => 'Correo',
    'stakeholder_phone' => 'Teléfono',
    'stakeholder_power' => 'Poder',
    'stakeholder_power_help' => 'Qué tanto puede decidir, detener o desbloquear este proyecto. 1 es nada, 5 es que su sola palabra lo para.',
    'stakeholder_interest' => 'Interés',
    'stakeholder_interest_help' => 'Qué tanto le afecta el resultado. 1 es que le da igual, 5 es que su día a día cambia.',
    'stakeholder_expectations' => 'Qué espera de este proyecto',
    'stakeholder_strategy' => 'Cómo tratarlo',
    'stakeholder_strategy_help' => 'El sistema propone una estrategia según dónde cayó en la matriz. Puedes cambiarla.',
    'stakeholder_created' => 'Interesado agregado.',
    'stakeholder_updated' => 'Interesado actualizado.',
    'stakeholder_deleted' => 'Interesado eliminado.',
    'no_stakeholders' => 'Todavía no hay interesados. Empieza por el más obvio: quien autoriza el presupuesto.',

    'matrix_title' => 'Matriz poder / interés',
    'matrix_help' => 'Cada punto es un interesado. Mientras más arriba y más a la derecha, más atención necesita.',
    'matrix_axis_power' => 'Poder →',
    'matrix_axis_interest' => 'Interés →',

    'quadrant_manage_closely' => 'Gestionar de cerca',
    'quadrant_keep_satisfied' => 'Mantener satisfecho',
    'quadrant_keep_informed' => 'Mantener informado',
    'quadrant_monitor' => 'Vigilar',

    'strategy_manage_closely' => 'Involucrarlo en las decisiones y reportarle de forma directa y frecuente. Si se entera tarde de algo, se entera molesto.',
    'strategy_keep_satisfied' => 'Puede detener el proyecto y no le interesa el detalle. Consultarlo en los puntos de decisión, sin saturarlo con avances.',
    'strategy_keep_informed' => 'Le afecta el resultado pero no decide. Informarle con regularidad; su apoyo cuesta poco y vale mucho.',
    'strategy_monitor' => 'Ni decide ni le afecta demasiado hoy. Revisar de vez en cuando por si eso cambia.',

    // --- Paso 3: acta ---

    'charter_intro' => 'Esto ya está casi armado con lo que capturaste. Revísalo y ajústalo — no lo escribas de cero.',
    'field_objectives' => 'Objetivos',
    'help_objectives' => 'Qué se quiere lograr. Dos o tres, no diez. Si son diez, son varios proyectos.',
    'field_deliverables' => 'Entregables',
    'help_deliverables' => 'Cosas que se pueden revisar y aprobar, no actividades. «Manual entregado», no «capacitar».',
    'field_success_criteria' => 'Cómo sabremos que salió bien',
    'help_success_criteria' => 'La prueba concreta. Si no se puede verificar, no es un criterio: es un deseo.',
    'field_assumptions' => 'Supuestos',
    'help_assumptions' => 'Lo que estás dando por hecho. Cada supuesto que falle se convierte en un problema, así que conviene escribirlos.',
    'field_constraints' => 'Restricciones',
    'help_constraints' => 'Lo que no se puede mover: fecha, presupuesto, gente disponible, reglas.',
    'field_out_of_scope' => 'Qué NO incluye',
    'help_out_of_scope' => 'El campo más útil del acta. Lo que aquí no esté escrito, alguien va a suponer que sí entraba.',
    'field_high_level_milestones' => 'Hitos principales',
    'help_high_level_milestones' => 'Cuatro o cinco momentos con fecha aproximada. El detalle se planea después.',
    'field_sponsor' => 'Patrocinador',
    'help_sponsor' => 'Quien autoriza el presupuesto y destraba lo que el equipo no puede. Sin patrocinador, el proyecto se para en el primer obstáculo.',

    'suggest' => 'Sugerir un borrador',
    'suggest_help' => 'Propone un texto a partir de lo que ya escribiste. Revísalo siempre: es un borrador, no una respuesta.',
    'suggestion_ready' => 'Borrador propuesto abajo. Revísalo y ajústalo antes de guardar.',
    'suggestion_empty' => 'No hubo suficiente contexto para proponer algo. Escribe primero el problema y vuelve a intentar.',
    'suggest_risks' => 'Sugerir riesgos típicos',
    'suggest_stakeholders' => 'Sugerir interesados típicos',
    'suggestions_added' => 'Se agregaron :count sugerencias. Revísalas: borra las que no apliquen y ajusta las demás.',
    'suggestions_none' => 'No hubo nada nuevo que sugerir.',

    // --- Paso 4: riesgos ---

    'risks_intro' => 'Un riesgo es algo que todavía no pasa y que, si pasa, cambia el resultado. También cuentan los buenos: una oportunidad que no se aprovecha se pierde igual.',
    'add_risk' => 'Agregar riesgo',
    'edit_risk' => 'Editar riesgo',
    'risk_code' => 'Clave',
    'risk_description' => 'Qué podría pasar',
    'risk_description_help' => 'Concreto. «El proveedor entrega tarde» no sirve; di qué proveedor, de qué, y por qué podría pasar.',
    'risk_cause' => 'Por qué podría pasar',
    'risk_effect' => 'Qué pasaría entonces',
    'risk_category' => 'Categoría',
    'risk_probability' => 'Probabilidad',
    'risk_probability_help' => '1 es casi imposible, 5 es que sería raro que no pasara.',
    'risk_impact' => 'Impacto',
    'risk_impact_help' => '1 es que casi no se nota, 5 es que el proyecto se cae.',
    'risk_kind' => 'Tipo',
    'risk_kind_threat' => 'Amenaza',
    'risk_kind_opportunity' => 'Oportunidad',
    'risk_status' => 'Estado',
    'risk_owner' => 'Responsable',
    'risk_owner_help' => 'Quién vigila este riesgo. Sin nombre, nadie lo vigila.',
    'risk_score' => 'Nivel',
    'risk_created' => 'Riesgo agregado.',
    'risk_updated' => 'Riesgo actualizado.',
    'risk_deleted' => 'Riesgo eliminado.',
    'no_risks' => 'Todavía no hay riesgos registrados. Un proyecto sin riesgos no existe; lo que no existe es la lista.',

    'status_identified' => 'Identificado',
    'status_analyzing' => 'En análisis',
    'status_responding' => 'Con respuesta en marcha',
    'status_closed' => 'Cerrado',
    'status_materialized' => 'Se materializó',

    'level_low' => 'Bajo',
    'level_medium' => 'Medio',
    'level_high' => 'Alto',
    'level_critical' => 'Crítico',

    'matrix_risk_title' => 'Matriz de probabilidad e impacto',
    'matrix_risk_help' => 'Los de la esquina superior derecha son los que necesitan un plan, no una nota.',

    'add_response' => 'Definir qué se hará',
    'response_strategy' => 'Estrategia',
    'response_description' => 'Qué se hará exactamente',
    'response_owner' => 'Responsable de hacerlo',
    'response_due' => 'Para cuándo',
    'response_created' => 'Respuesta registrada.',
    'response_deleted' => 'Respuesta eliminada.',
    'no_responses' => 'Sin respuesta definida',

    'strategy_avoid' => 'Evitar — cambiar el plan para que no pueda pasar',
    'strategy_mitigate' => 'Mitigar — bajar la probabilidad o el impacto',
    'strategy_transfer' => 'Transferir — que lo asuma un tercero (seguro, contrato)',
    'strategy_accept' => 'Aceptar — convivir con él y vigilarlo',
    'strategy_escalate' => 'Escalar — no le toca a este proyecto resolverlo',

    // --- Semáforo de completitud ---

    'health' => 'Qué falta',
    'health_green' => 'El inicio está completo. Puedes presentarlo.',
    'health_amber' => 'Se puede presentar, pero hay detalles que conviene cerrar.',
    'health_red' => 'Todavía falta algo que el documento necesita para sostenerse.',
    'health_complete_pct' => ':percent % del recorrido',
    'why' => 'Por qué importa',

    'finding_no_problem' => 'No está escrito cuál es el problema.',
    'finding_no_problem_why' => 'Sin problema no hay proyecto que defender. Es lo primero que va a preguntar quien autorice el presupuesto.',
    'finding_no_benefit' => 'No está escrito qué se gana.',
    'finding_no_benefit_why' => 'Un proyecto sin beneficio declarado no se puede priorizar contra otro, y al cerrar nadie sabrá si valió la pena.',
    'finding_no_alignment' => 'No se declaró con qué objetivo de la empresa se alinea.',
    'finding_no_alignment_why' => 'Es lo que separa un proyecto necesario de uno que solo le importa a un área.',

    'finding_no_stakeholders' => 'No hay ningún interesado registrado.',
    'finding_no_stakeholders_why' => 'Los proyectos rara vez fallan por lo técnico. Fallan porque alguien que podía detenerlos se enteró tarde.',
    'finding_no_key_stakeholder' => 'Nadie quedó en «gestionar de cerca».',
    'finding_no_key_stakeholder_why' => 'Si de verdad nadie tiene poder e interés altos, puede que falte registrar a quien autoriza.',
    'finding_no_strategy' => 'Sin estrategia definida: :names',
    'finding_no_strategy_why' => 'Son los que pueden hundir o salvar el proyecto. Decidir cómo tratarlos no puede quedar a la improvisación.',

    'finding_no_objectives' => 'Faltan los objetivos.',
    'finding_no_objectives_why' => 'Sin objetivos el equipo no sabe hacia dónde, y cada quien empuja hacia su idea.',
    'finding_no_deliverables' => 'Faltan los entregables.',
    'finding_no_deliverables_why' => 'Son lo que se revisa y se aprueba. Sin ellos no hay forma de decir que algo está terminado.',
    'finding_no_success_criteria' => 'Falta cómo se sabrá que salió bien.',
    'finding_no_success_criteria_why' => 'Es lo que evita la discusión al cierre sobre si el proyecto cumplió o no.',
    'finding_no_sponsor' => 'No hay patrocinador designado.',
    'finding_no_sponsor_why' => 'Es quien destraba lo que el equipo no puede. Sin él, el proyecto se detiene en el primer obstáculo.',

    'finding_few_risks' => 'Solo hay :count riesgo(s) registrado(s); se esperan al menos :minimum.',
    'finding_few_risks_why' => 'Todo proyecto tiene riesgos. Una lista corta casi nunca significa que haya pocos, sino que nadie los buscó.',
    'finding_risk_without_response' => 'Riesgos altos sin respuesta: :codes',
    'finding_risk_without_response_why' => 'Un riesgo alto sin plan es el hallazgo más común de una auditoría, y el más fácil de evitar.',
    'finding_risk_without_owner' => 'Riesgos sin responsable: :codes',
    'finding_risk_without_owner_why' => 'Un riesgo sin nombre no lo vigila nadie, aunque esté escrito.',

    // --- Salida ---

    'package' => 'Paquete de inicio',
    'download_package' => 'Ver el paquete completo',
    'print_hint' => 'Usa la impresión del navegador para guardarlo como PDF.',
    'generated_on' => 'Generado el :date',
    'prepared_by' => 'Preparado por',
    'unnamed_stakeholder' => 'Interesado sin nombre',

    'approve' => 'Aprobar el acta',
    'approved_on' => 'Aprobada el :date por :name',
    'not_approved' => 'Sin aprobar',
    'approved' => 'Acta aprobada.',
    'cannot_approve_incomplete' => 'No se puede aprobar mientras falte algo obligatorio. Revisa la lista de pendientes.',

    'suggestion_applied' => 'Borrador aplicado. Revisalo antes de darlo por bueno.',
];
