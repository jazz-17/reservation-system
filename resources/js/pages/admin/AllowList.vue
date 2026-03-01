<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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
import adminAllowListRoutes from '@/routes/admin/allow-list';
import adminRequestsRoutes from '@/routes/admin/requests';
import type { AllowListEntry } from '@/types/admin';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    count: number;
    entries: {
        data: AllowListEntry[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    filters: { search: string };
    schools: Array<{
        id: number;
        name: string;
        code: string | null;
        base_year_min: number;
        base_year_max: number;
    }>;
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
                    @click="search = ''; submitSearch()"
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
                    <TableCell colspan="4">
                        <Form
                            v-bind="adminAllowListRoutes.update.form(entry.id)"
                            v-slot="{ errors, processing }"
                            class="grid grid-cols-[1fr_1fr_auto_auto] items-center gap-2"
                        >
                            <div class="grid gap-1">
                                <Input
                                    name="email"
                                    type="email"
                                    :default-value="entry.email"
                                    required
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div class="grid gap-1">
                                <NativeSelect
                                    name="professional_school_id"
                                    :default-value="entry.professional_school?.id ? String(entry.professional_school.id) : ''"
                                    required
                                >
                                    <option value="" disabled>—</option>
                                    <option
                                        v-for="s in props.schools"
                                        :key="s.id"
                                        :value="String(s.id)"
                                    >
                                        {{ s.name }}
                                    </option>
                                </NativeSelect>
                                <InputError :message="errors.professional_school_id" />
                            </div>

                            <div class="grid gap-1">
                                <Input
                                    name="student_code"
                                    type="text"
                                    inputmode="numeric"
                                    class="w-28"
                                    :default-value="entry.student_code ?? ''"
                                    required
                                />
                                <InputError :message="errors.student_code" />
                            </div>

                            <div class="flex items-center gap-1">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    Guardar
                                </Button>
                            </div>
                        </Form>
                    </TableCell>
                    <TableCell class="text-right">
                        <ConfirmDialog
                            title="¿Eliminar esta entrada?"
                            description="Esta acción no se puede deshacer."
                            confirm-label="Eliminar"
                            variant="destructive"
                            @confirm="remove(entry.id)"
                        >
                            <template #trigger>
                                <Button type="button" variant="ghost" size="sm">
                                    Eliminar
                                </Button>
                            </template>
                        </ConfirmDialog>
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.entries.data.length === 0" :colspan="5">
                    Sin entradas.
                </TableEmpty>
            </TableBody>
        </Table>

        <div
            v-if="props.entries.last_page > 1"
            class="flex items-center justify-center gap-1"
        >
            <template v-for="link in props.entries.links" :key="link.label">
                <Button
                    v-if="link.url"
                    variant="outline"
                    size="sm"
                    :class="{ 'font-bold': link.active }"
                    @click="router.get(link.url!)"
                >
                    <span v-html="link.label" />
                </Button>
                <span
                    v-else
                    class="px-2 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
