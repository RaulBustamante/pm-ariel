<?php

declare(strict_types=1);

return [

    'title' => 'Usuarios',
    'create_title' => 'Nuevo usuario',
    'edit_title' => 'Editar usuario',
    'create_action' => 'Crear usuario',

    'roles' => 'Roles',
    'roles_help' => 'Un rol agrupa permisos. Los costos son un permiso aparte: un jefe puede ver el avance de su gente sin ver tarifas.',

    'created_with_password' => 'Usuario :email creado. Contraseña temporal: :password — anótala ahora, no se vuelve a mostrar y debe cambiarse al primer acceso.',
    'updated' => 'Usuario actualizado.',

    'password_title' => 'Contraseña',
    'password_help' => 'Para cuando alguien la olvidó y el correo de recuperación no es una opción. La actual no se puede consultar: no se guarda en ningún lado, solo su huella.',
    'password_new' => 'Contraseña nueva',
    'password_confirm' => 'Repite la contraseña',
    'password_force_change' => 'Obligar a cambiarla al primer acceso',
    'password_force_change_help' => 'Mientras siga vigente la que tú pusiste, dos personas conocen la cuenta.',
    'password_set_action' => 'Guardar contraseña',
    'password_set' => 'Contraseña de :email actualizada.',
    'password_reset_action' => 'Generar temporal',
    'password_reset_help' => 'Genera una contraseña al azar y la muestra una sola vez. Siempre obliga a cambiarla al entrar.',
    'password_reset_confirm' => '¿Generar una contraseña nueva? La actual deja de funcionar de inmediato.',
    'password_reset_with_password' => 'Contraseña de :email restablecida. Nueva contraseña temporal: :password — anótala ahora, no se vuelve a mostrar.',
    'password_not_managed' => 'La cuenta :email la administra el proveedor de identidad: su contraseña no se cambia desde aquí.',

    'no_roles' => 'Sin roles',
    'never_signed_in' => 'Nunca ha entrado',

];
