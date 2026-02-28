<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import {
    index as facultiesIndex,
    store,
    update,
} from '@/routes/admin/faculties';
import { index as adminRequestsIndex } from '@/routes/admin/requests';
import type { Faculty } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsIndex().url },
    { title: 'Facultades', href: facultiesIndex().url },
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
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-[1fr_auto_auto]"
            >
                <div class="grid gap-1">
                    <label class="text-sm" for="name">Nombre</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="Facultad de ..."
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="active" value="0" />
                        <input
                            type="checkbox"
                            name="active"
                            value="1"
                            checked
                        />
                        Activa
                    </label>
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
                            v-bind="update.form(f.id)"
                            v-slot="{ errors, processing }"
                            class="flex flex-col gap-1 md:flex-row md:items-center md:gap-2"
                        >
                            <input
                                name="name"
                                type="text"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm md:w-[380px]"
                                :value="f.name"
                                required
                            />
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-2 text-sm">
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
                                </label>

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
