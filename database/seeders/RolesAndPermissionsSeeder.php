<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.panel.access',
            'admin.reservas.solicitudes.view',
            'admin.reservas.solicitudes.decide',
            'admin.reservas.historial.view',
            'admin.reservas.reintentos.view',
            'admin.reservas.reintentos.retry',
            'admin.gestion.configuracion.manage',
            'admin.gestion.facultades.manage',
            'admin.gestion.escuelas.manage',
            'admin.gestion.allow_list.view',
            'admin.gestion.allow_list.import',
            'admin.gestion.blackouts.manage',
            'admin.supervision.auditoria.view',
            'reservations.cancel.any',
            'reservations.view_pdf.any',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            'admin' => $permissions,
            'operator' => [
                'admin.panel.access',
                'admin.reservas.solicitudes.view',
                'admin.reservas.solicitudes.decide',
                'admin.reservas.historial.view',
                'admin.reservas.reintentos.view',
                'admin.reservas.reintentos.retry',
                'reservations.cancel.any',
                'reservations.view_pdf.any',
            ],
            'auditor' => [
                'admin.panel.access',
                'admin.reservas.solicitudes.view',
                'admin.reservas.historial.view',
                'admin.reservas.reintentos.view',
                'admin.supervision.auditoria.view',
                'reservations.view_pdf.any',
            ],
            'student' => [],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
