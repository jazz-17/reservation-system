<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import adminAllowListRoutes from '@/routes/admin/allow-list';
import adminRequestsRoutes from '@/routes/admin/requests';
import type { AllowListEntry, PaginatedResponse } from '@/types/admin';

const props = defineProps<{
    count: number;
    entries: PaginatedResponse<AllowListEntry>;
    filters: { search: string };
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Allow-list', href: adminAllowListRoutes.index().url },
]);

const search = ref(props.filters.search);

const submitSearch = (): void => {
    router.get(
        adminAllowListRoutes.index().url,
        search.value.trim() ? { search: search.value.trim() } : {},
        { preserveState: true },
    );
};

const remove = (id: number): void => {
    router.delete(adminAllowListRoutes.destroy(id).url);
};
</script>

<template>
    <Head title="Allow-list" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Allow-list"
            :subtitle="`Correos permitidos para registrarse. Total: ${props.count}`"
        >
            <Button as-child>
                <Link :href="adminAllowListRoutes.create().url">
                    Agregar correo
                </Link>
            </Button>
        </AdminPageHeader>

        <AdminSection title="Entradas">
            <form class="mt-3 flex gap-2" @submit.prevent="submitSearch">
                <Input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por correo…"
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
                    @click="
                        search = '';
                        submitSearch();
                    "
                >
                    Limpiar
                </Button>
            </form>
        </AdminSection>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Correo</TableHead>
                    <TableHead>Escuela</TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Base</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="entry in props.entries.data" :key="entry.id">
                    <TableCell>{{ entry.email }}</TableCell>
                    <TableCell>{{
                        entry.professional_school?.name ?? '—'
                    }}</TableCell>
                    <TableCell>{{ entry.student_code ?? '—' }}</TableCell>
                    <TableCell>{{ entry.base_year ?? '—' }}</TableCell>
                    <TableCell class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        adminAllowListRoutes.edit(entry.id).url
                                    "
                                >
                                    Editar
                                </Link>
                            </Button>
                            <ConfirmDialog
                                title="¿Eliminar esta entrada?"
                                description="Esta acción no se puede deshacer."
                                confirm-label="Eliminar"
                                variant="destructive"
                                @confirm="remove(entry.id)"
                            >
                                <template #trigger>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        Eliminar
                                    </Button>
                                </template>
                            </ConfirmDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.entries.data.length === 0" :colspan="5">
                    Sin entradas.
                </TableEmpty>
            </TableBody>
            <TablePagination
                :links="props.entries.links"
                :last-page="props.entries.last_page"
                :colspan="5"
            />
        </Table>
    </div>
</template>
