<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Qué permisos trae cada rol semilla.
     *
     * Nota deliberada: `project_manager` NO trae permisos de costo. Ver costos
     * es un permiso independiente del nivel jerárquico y del rol de proyecto
     * (regla 3 de visibilidad, decisión D-012); se otorga a quien deba verlos,
     * no a quien dirija proyectos.
     *
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        $all = collect(Permission::catalog())->flatten()->all();

        return [
            Role::ADMIN => $all,

            Role::PORTFOLIO_MANAGER => [
                Permission::PROJECTS_VIEW, Permission::PROJECTS_MANAGE,
                Permission::TASKS_MANAGE, Permission::DEPENDENCIES_MANAGE,
                Permission::BASELINES_MANAGE, Permission::RESOURCES_MANAGE,
                Permission::COSTS_VIEW, Permission::COSTS_MANAGE,
                Permission::REPORTS_EXPORT,
            ],

            Role::PROJECT_MANAGER => [
                Permission::PROJECTS_VIEW, Permission::PROJECTS_MANAGE,
                Permission::TASKS_MANAGE, Permission::DEPENDENCIES_MANAGE,
                Permission::BASELINES_MANAGE, Permission::RESOURCES_MANAGE,
                Permission::REPORTS_EXPORT,
            ],

            Role::TEAM_MEMBER => [
                Permission::PROJECTS_VIEW, Permission::TASKS_MANAGE,
            ],

            Role::VIEWER => [
                Permission::PROJECTS_VIEW,
            ],

            // Lectura global. La prohibición de escribir no vive en los permisos
            // sino en las Policies, para que ningún permiso añadido después la
            // rompa por descuido.
            Role::AUDITOR => [
                Permission::PROJECTS_VIEW, Permission::AUDIT_VIEW_ALL, Permission::REPORTS_EXPORT,
            ],
        ];
    }

    public function run(): void
    {
        foreach (Permission::catalog() as $group => $names) {
            foreach ($names as $name) {
                Permission::query()->firstOrCreate(['name' => $name], ['group' => $group]);
            }
        }

        foreach ($this->rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => true]);

            $ids = Permission::query()->whereIn('name', $permissionNames)->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
