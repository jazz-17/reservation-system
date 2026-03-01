<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import adminFacultiesRoutes from '@/routes/admin/faculties';
import adminRequestsRoutes from '@/routes/admin/requests';
import type { Faculty } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Facultades', href: adminFacultiesRoutes.index().url },
]);
</script>

<template>
    <Head title="Facultades" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Facultades"
            subtitle="Administra las facultades disponibles para el registro."
        />

        <AdminSection title="Nueva facultad">
            <Form
                v-bind="adminFacultiesRoutes.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-[1fr_auto_auto]"
            >
                <div class="grid gap-1">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        placeholder="Facultad de ..."
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex items-end">
                    <Label class="flex items-center gap-2">
                        <input type="hidden" name="active" value="0" />
                        <input
                            type="checkbox"
                            name="active"
                            value="1"
                            checked
                        />
                        Activa
                    </Label>
                </div>

                <div class="flex items-end justify-end">
                    <Button type="submit" :disabled="processing">
                        Crear
                    </Button>
                </div>
            </Form>
        </AdminSection>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Activa</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="f in props.faculties" :key="f.id">
                    <TableCell>
                        <Form
                            v-bind="adminFacultiesRoutes.update.form(f.id)"
                            v-slot="{ errors, processing }"
                            class="flex flex-col gap-1 md:flex-row md:items-center md:gap-2"
                        >
                            <Input
                                name="name"
                                type="text"
                                class="md:w-[380px]"
                                :default-value="f.name"
                                required
                            />
                            <div class="flex items-center gap-2">
                                <Label class="flex items-center gap-2">
                                    <input
                                        type="hidden"
                                        name="active"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="active"
                                        value="1"
                                        :checked="f.active"
                                    />
                                    Activa
                                </Label>

                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    Guardar
                                </Button>
                            </div>

                            <div class="text-xs text-destructive">
                                <div v-if="errors.name">
                                    {{ errors.name }}
                                </div>
                            </div>
                        </Form>
                    </TableCell>
                    <TableCell>
                        {{ f.active ? 'Sí' : 'No' }}
                    </TableCell>
                    <TableCell class="text-right text-xs text-muted-foreground">
                        ID: {{ f.id }}
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.faculties.length === 0" :colspan="3">
                    Sin registros.
                </TableEmpty>
            </TableBody>
        </Table>
    </div>
</template>
