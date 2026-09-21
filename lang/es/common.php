<?php

declare(strict_types=1);

return [

    'dashboard' => 'Inicio',
    'administration' => 'Administración',
    'users' => 'Usuarios',
    'roles' => 'Roles',
    'organization' => 'Organización',
    'audit_log' => 'Bitácora de auditoría',
    'projects' => 'Proyectos',

    'save' => 'Guardar',
    'saved' => 'Guardado.',
    'cancel' => 'Cancelar',
    'create' => 'Crear',
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'deactivate' => 'Desactivar',
    'activate' => 'Activar',
    'search' => 'Buscar',
    'actions' => 'Acciones',
    'yes' => 'Sí',
    'no' => 'No',

    'name' => 'Nombre',
    'email' => 'Correo',
    'status' => 'Estado',
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'position' => 'Puesto',
    'org_unit' => 'Área',
    'manager' => 'Jefe directo',
    'language' => 'Idioma',
    'timezone' => 'Zona horaria',
    'preferences' => 'Preferencias',

    // El idioma se escribe siempre en su propio idioma: quien entró por error
    // a una interfaz que no lee, reconoce igual el suyo en la lista.
    'locale_es' => 'Español',
    'locale_en' => 'English',

    // El nivel de detalle de un proyecto. «Estándar» nombra el caso normal, no
    // el disminuido, y «Especialista» nombra un oficio, no un rango: quien usa
    // el sistema sin ruta crítica no está en la versión para principiantes.
    'detail_level' => 'Nivel de detalle',
    'detail_standard' => 'Estándar',
    'detail_specialist' => 'Especialista',
    'detail_help' => 'En Estándar se ven fechas, responsables y avance. En Especialista se agregan holguras, restricciones y tipos de dependencia. Se cambia cuando quieras y no se pierde nada al bajarlo.',

    // Preferencia personal, que solo decide con qué nivel nacen los proyectos
    // que tú creas. El nivel de cada proyecto se manda solo.
    'simple_mode' => 'Estándar',
    'expert_mode' => 'Especialista',

    // Estados vacíos: qué es, por qué está vacío, qué hacer. Nunca una pantalla en blanco.
    'empty_title' => 'Todavía no hay nada aquí',
    'empty_users' => 'Aquí se administran las personas que pueden entrar al sistema y qué puede hacer cada una.',
    'empty_action' => 'Empieza creando el primero.',

    'confirm_title' => '¿Seguro?',
    'skip_to_content' => 'Ir al contenido',

];
