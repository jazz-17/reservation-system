<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import TablePagination from '@/components/admin/TablePagination.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatDate, formatDateTime } from '@/lib/formatters';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminUsersRoutes from '@/routes/admin/users';
import type { ManagedUser, PaginatedResponse } from '@/types/admin';

const props = defineProps<{
    users: PaginatedResponse<ManagedUser>;
    filters: { search: string };
    available_roles: string[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Usuarios', href: adminUsersRoutes.index().url },
]);

const search = ref(props.filters.search);

// Dialog state management for per-user role modals
const rolesDialogOpen = reactive<Record<number, boolean>>({});

const openRolesDialog = (userId: number): void => {
    rolesDialogOpen[userId] = true;
};

const closeRolesDialog = (userId: number): void => {
    rolesDialogOpen[userId] = false;
};

const submitSearch = (): void => {
    router.get(
        adminUsersRoutes.index().url,
        search.value.trim() ? { search: search.value.trim() } : {},
        { preserveState: true },
    );
};

const isDisabled = (user: ManagedUser): boolean => Boolean(user.disabled_at);
const isVerified = (user: ManagedUser): boolean => Boolean(user.email_verified_at);
const isProtected = (user: ManagedUser): boolean => Boolean(user.is_protected);

const rolesFor = (user: ManagedUser): string[] => {
    return Array.isArray(user.roles) ? user.roles : [];
};

const roleBadgeVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    if (role === 'admin') {
        return 'default';
    }

    if (role === 'operator') {
        return 'secondary';
    }

    return 'outline';
};

const canResendVerification = (user: ManagedUser): boolean => !isVerified(user);

const selectedRoles = (user: ManagedUser) => {
    const current = new Set(rolesFor(user));
    return props.available_roles.map((role) => ({
        role,
        checked: current.has(role),
    }));
};

const hasStaffRole = (roles: string[]): boolean =>
    roles.some((r) => ['admin', 'operator'].includes(r));

const roleHelpText = computed(() => {
    return 'Roles fijos. Si asignas un rol staff (admin/operator), el rol student se removerá automáticamente.';
});

const eventTypeLabels: Record<string, string> = {
    'user.roles_updated': 'Roles actualizados',
    'user.disabled': 'Desactivado',
    'user.enabled': 'Activado',
    'user.password_reset_sent': 'Reset de contraseña',
    'user.verification_sent': 'Verificación enviada',
};

const formatEventType = (eventType: string): string => {
    return eventTypeLabels[eventType] ?? eventType;
};

const hasActivity = (user: ManagedUser): boolean => {
    return user.recent_activity && user.recent_activity.length > 0;
};

const toggleDisabled = (user: ManagedUser, disabled: boolean): void => {
    router.put(
        adminUsersRoutes.status.update(user.id).url,
        { disabled },
        { preserveScroll: true },
    );
};

const sendPasswordReset = (user: ManagedUser): void => {
    router.post(
        adminUsersRoutes.passwordReset.store(user.id).url,
        {},
        { preserveScroll: true },
    );
};

const resendVerification = (user: ManagedUser): void => {
    router.post(
        adminUsersRoutes.emailVerification.store(user.id).url,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Usuarios"
            subtitle="Asigna roles, activa/desactiva acceso, y ayuda con verificación y recuperación de contraseña."
        />

        <AdminSection title="Búsqueda">
            <form class="mt-3 flex gap-2" @submit.prevent="submitSearch">
                <Input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por nombre o correo…"
                    class="max-w-sm"
                />
                <Button type="submit" variant="outline" size="sm">
                    Buscar
                </Button>
                <Button
                    v-if="props.filters.search"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="search = ''; submitSearch()"
                >
                    Limpiar
                </Button>
            </form>
        </AdminSection>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Usuario</TableHead>
                    <TableHead>Roles</TableHead>
                    <TableHead>Verificado</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead class="text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="u in props.users.data" :key="u.id">
                    <TableCell>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 font-medium">
                                {{ u.name }}
                                <Badge
                                    v-if="isProtected(u)"
                                    variant="outline"
                                    class="text-xs"
                                >
                                    Protegido
                                </Badge>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ u.email }}
                            </div>
                            <div v-if="u.created_at" class="text-xs text-muted-foreground">
                                Creado: {{ formatDate(u.created_at) }}
                            </div>
                            <Collapsible v-if="hasActivity(u)" class="mt-1">
                                <CollapsibleTrigger class="text-xs text-primary hover:underline">
                                    Ver actividad reciente ({{ u.recent_activity.length }})
                                </CollapsibleTrigger>
                                <CollapsibleContent class="mt-1 space-y-1">
                                    <div
                                        v-for="(activity, idx) in u.recent_activity"
                                        :key="idx"
                                        class="flex flex-col rounded bg-muted/50 px-2 py-1 text-xs"
                                    >
                                        <span class="font-medium">
                                            {{ formatEventType(activity.event_type) }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            {{ activity.actor_name ?? 'Sistema' }}
                                            <template v-if="activity.created_at">
                                                — {{ formatDateTime(activity.created_at) }}
                                            </template>
                                        </span>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </TableCell>

                    <TableCell>
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="role in rolesFor(u)"
                                :key="role"
                                :variant="roleBadgeVariant(role)"
                            >
                                {{ role }}
                            </Badge>
                            <span
                                v-if="rolesFor(u).length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                —
                            </span>
                        </div>
                    </TableCell>

                    <TableCell>
                        <span
                            v-if="isVerified(u)"
                            class="text-sm text-success"
                        >
                            Sí
                        </span>
                        <span
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            No
                        </span>
                    </TableCell>

                    <TableCell>
                        <span
                            v-if="isDisabled(u)"
                            class="text-sm text-destructive"
                        >
                            Desactivado
                        </span>
                        <span v-else class="text-sm text-success">Activo</span>
                    </TableCell>

                    <TableCell class="text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Dialog
                                :open="rolesDialogOpen[u.id] ?? false"
                                @update:open="(val: boolean) => val ? openRolesDialog(u.id) : closeRolesDialog(u.id)"
                            >
                                <DialogTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="isProtected(u)"
                                    >
                                        Roles
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <Form
                                        v-bind="adminUsersRoutes.roles.update.form(u.id)"
                                        v-slot="{ errors, processing }"
                                        :options="{ preserveScroll: true }"
                                        class="space-y-4"
                                        @success="closeRolesDialog(u.id)"
                                    >
                                        <DialogHeader class="space-y-2">
                                            <DialogTitle>Editar roles</DialogTitle>
                                            <DialogDescription>
                                                {{ roleHelpText }}
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div class="grid gap-3">
                                            <div
                                                v-for="r in selectedRoles(u)"
                                                :key="r.role"
                                                class="flex items-center gap-2"
                                            >
                                                <input
                                                    :id="`role-${u.id}-${r.role}`"
                                                    type="checkbox"
                                                    name="roles[]"
                                                    :value="r.role"
                                                    :checked="r.checked"
                                                    :disabled="processing"
                                                />
                                                <Label
                                                    :for="`role-${u.id}-${r.role}`"
                                                    class="flex items-center gap-2"
                                                >
                                                    {{ r.role }}
                                                </Label>
                                            </div>

                                            <div class="text-xs text-muted-foreground">
                                                Staff seleccionado:
                                                {{
                                                    hasStaffRole(rolesFor(u))
                                                        ? 'Sí'
                                                        : 'No'
                                                }}
                                            </div>
                                            <InputError :message="errors.roles" />
                                        </div>

                                        <DialogFooter class="gap-2">
                                            <DialogClose as-child>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    :disabled="processing"
                                                >
                                                    Cancelar
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" :disabled="processing">
                                                {{ processing ? 'Guardando...' : 'Guardar' }}
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <ConfirmDialog
                                :title="isDisabled(u) ? '¿Activar usuario?' : '¿Desactivar usuario?'"
                                :description="isDisabled(u) ? 'El usuario podrá iniciar sesión nuevamente.' : 'El usuario no podrá iniciar sesión. Sus sesiones activas serán cerradas.'"
                                :confirm-label="isDisabled(u) ? 'Activar' : 'Desactivar'"
                                :variant="isDisabled(u) ? 'default' : 'destructive'"
                                :disabled="isProtected(u)"
                                @confirm="toggleDisabled(u, !isDisabled(u))"
                            >
                                <template #trigger>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="isDisabled(u) ? 'outline' : 'destructive'"
                                        :disabled="isProtected(u)"
                                    >
                                        {{ isDisabled(u) ? 'Activar' : 'Desactivar' }}
                                    </Button>
                                </template>
                            </ConfirmDialog>

                            <ConfirmDialog
                                title="¿Enviar enlace de restablecimiento?"
                                description="Se enviará un correo para restablecer la contraseña."
                                confirm-label="Enviar"
                                :disabled="isProtected(u)"
                                @confirm="sendPasswordReset(u)"
                            >
                                <template #trigger>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="isProtected(u)"
                                    >
                                        Reset
                                    </Button>
                                </template>
                            </ConfirmDialog>

                            <ConfirmDialog
                                v-if="canResendVerification(u)"
                                title="¿Reenviar verificación de correo?"
                                description="Se reenviará el correo de verificación."
                                confirm-label="Reenviar"
                                @confirm="resendVerification(u)"
                            >
                                <template #trigger>
                                    <Button type="button" variant="ghost" size="sm">
                                        Verificación
                                    </Button>
                                </template>
                            </ConfirmDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.users.data.length === 0" :colspan="5">
                    Sin resultados.
                </TableEmpty>
            </TableBody>
            <TablePagination
                :links="props.users.links"
                :last-page="props.users.last_page"
                :colspan="5"
            />
        </Table>
    </div>
</template>
