<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendUserEmailVerificationRequest;
use App\Http\Requests\Admin\SendUserPasswordResetRequest;
use App\Http\Requests\Admin\ToggleUserStatusRequest;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'disabled_at',
                'is_protected',
                'created_at',
            ])
            ->with([
                'roles:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $q = Str::lower($search);

                $query->where(function ($sub) use ($q): void {
                    $sub->where('email', 'ilike', "%{$q}%")
                        ->orWhere('name', 'ilike', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (User $user): array {
                /** @var array<int, string> $roles */
                $roles = $user->roles->pluck('name')->values()->all();

                $recentActivity = AuditEvent::query()
                    ->where('subject_type', User::class)
                    ->where('subject_id', $user->id)
                    ->orderByDesc('created_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (AuditEvent $e): array => [
                        'event_type' => $e->event_type,
                        'actor_name' => $e->actor?->name,
                        'created_at' => $e->created_at?->toISOString(),
                    ])
                    ->all();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'disabled_at' => $user->disabled_at?->toISOString(),
                    'is_protected' => $user->is_protected,
                    'created_at' => $user->created_at?->toISOString(),
                    'recent_activity' => $recentActivity,
                ];
            });

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
            'available_roles' => ['admin', 'operator', 'auditor', 'student'],
        ]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($user->isProtected()) {
            return back()->withErrors(['roles' => 'Este usuario está protegido y no se pueden modificar sus roles.']);
        }

        /** @var array<int, string> $roles */
        $roles = $request->validated('roles', []);
        $roles = array_values(array_unique(array_map(static fn (string $r): string => Str::lower(trim($r)), $roles)));

        if (count($roles) === 0) {
            return back()->withErrors(['roles' => 'Selecciona al menos un rol.']);
        }

        if (in_array('student', $roles, true) && count($roles) > 1) {
            $roles = array_values(array_filter($roles, static fn (string $r): bool => $r !== 'student'));
        }

        if ($actor->id === $user->id && ! in_array('admin', $roles, true)) {
            return back()->withErrors(['roles' => 'No puedes quitarte el rol de admin.']);
        }

        $isRemovingAdmin = $user->hasRole('admin') && ! in_array('admin', $roles, true);
        if ($isRemovingAdmin && ! User::query()->whereKeyNot($user->id)->role('admin')->exists()) {
            return back()->withErrors(['roles' => 'No puedes quitar el rol de admin al último administrador.']);
        }

        $user->syncRoles($roles);

        Audit::record('user.roles_updated', actor: $actor, subject: $user, metadata: [
            'target_user_id' => $user->id,
            'roles' => $roles,
        ]);

        return back()->with('success', 'Roles actualizados correctamente.');
    }

    public function toggleStatus(ToggleUserStatusRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($user->isProtected()) {
            return back()->withErrors(['disabled' => 'Este usuario está protegido y no se puede cambiar su estado.']);
        }

        $disabled = (bool) $request->validated('disabled');

        if ($actor->id === $user->id) {
            return back()->withErrors(['disabled' => 'No puedes desactivar tu propia cuenta.']);
        }

        if ($disabled && $user->hasRole('admin') && ! User::query()->whereKeyNot($user->id)->role('admin')->exists()) {
            return back()->withErrors(['disabled' => 'No puedes desactivar al último administrador.']);
        }

        if ($disabled) {
            $user->forceFill(['disabled_at' => now()])->save();

            $table = (string) config('session.table', 'sessions');
            try {
                DB::table($table)->where('user_id', $user->id)->delete();
            } catch (\Throwable) {
            }

            Audit::record('user.disabled', actor: $actor, subject: $user, metadata: [
                'target_user_id' => $user->id,
            ]);

            return back()->with('success', 'Usuario desactivado.');
        }

        $user->forceFill(['disabled_at' => null])->save();

        Audit::record('user.enabled', actor: $actor, subject: $user, metadata: [
            'target_user_id' => $user->id,
        ]);

        return back()->with('success', 'Usuario activado.');
    }

    public function sendPasswordReset(SendUserPasswordResetRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($user->isProtected()) {
            return back()->withErrors(['email' => 'Este usuario está protegido y no se puede enviar restablecimiento de contraseña.']);
        }

        $status = Password::sendResetLink([
            'email' => (string) $user->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => 'No se pudo enviar el enlace de restablecimiento.']);
        }

        Audit::record('user.password_reset_sent', actor: $actor, subject: $user, metadata: [
            'target_user_id' => $user->id,
        ]);

        return back()->with('success', 'Enlace de restablecimiento enviado.');
    }

    public function sendEmailVerification(SendUserEmailVerificationRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', 'El usuario ya está verificado.');
        }

        $user->sendEmailVerificationNotification();

        Audit::record('user.verification_sent', actor: $actor, subject: $user, metadata: [
            'target_user_id' => $user->id,
        ]);

        return back()->with('success', 'Correo de verificación reenviado.');
    }
}
