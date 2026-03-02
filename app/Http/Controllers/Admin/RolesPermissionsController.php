<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolesPermissionsController extends Controller
{
    public function index(): Response
    {
        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->with(['permissions:id,name'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/RolesPermissions', [
            'roles' => $roles->map(static fn (Role $role): array => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->values()->all(),
        ]);
    }
}
