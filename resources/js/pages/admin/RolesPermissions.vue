<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminRolesPermissionsRoutes from '@/routes/admin/roles-permissions';

const props = defineProps<{
    roles: Array<{
        name: string;
        permissions: string[];
    }>;
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Roles y permisos', href: adminRolesPermissionsRoutes.index().url },
]);
</script>

<template>
    <Head title="Roles y permisos" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Roles y permisos"
            subtitle="Vista solo lectura del mapa rol → permisos (fijos)."
        />

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Rol</TableHead>
                    <TableHead>Permisos</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="r in props.roles" :key="r.name">
                    <TableCell class="font-medium">
                        {{ r.name }}
                    </TableCell>
                    <TableCell>
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="p in r.permissions"
                                :key="p"
                                class="rounded border border-border/60 px-2 py-0.5 text-xs"
                            >
                                {{ p }}
                            </span>
                            <span
                                v-if="r.permissions.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                —
                            </span>
                        </div>
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.roles.length === 0" :colspan="2">
                    Sin datos.
                </TableEmpty>
            </TableBody>
        </Table>
    </div>
</template>

