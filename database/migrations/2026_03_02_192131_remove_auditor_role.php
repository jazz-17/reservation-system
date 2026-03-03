<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Give operator the audit-view permission
        $operator = Role::query()->where('name', 'operator')->first();
        if ($operator) {
            $operator->givePermissionTo('admin.supervision.auditoria.view');
        }

        // Reassign any auditor users to operator (safety net)
        $auditor = Role::query()->where('name', 'auditor')->first();
        if ($auditor) {
            $auditorUsers = $auditor->users;
            foreach ($auditorUsers as $user) {
                $user->syncRoles(['operator']);
            }

            $auditor->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $auditor = Role::query()->firstOrCreate([
            'name' => 'auditor',
            'guard_name' => 'web',
        ]);

        $auditor->syncPermissions([
            'admin.panel.access',
            'admin.reservas.solicitudes.view',
            'admin.reservas.historial.view',
            'admin.reservas.reintentos.view',
            'admin.supervision.auditoria.view',
            'reservations.view_pdf.any',
        ]);

        // Remove audit permission from operator
        $operator = Role::query()->where('name', 'operator')->first();
        if ($operator) {
            $operator->revokePermissionTo('admin.supervision.auditoria.view');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
