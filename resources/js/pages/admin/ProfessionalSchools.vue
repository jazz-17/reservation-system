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
import { formatBaseYear } from '@/lib/formatters';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminSchoolsRoutes from '@/routes/admin/schools';
import type { Faculty, ProfessionalSchool } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
    schools: ProfessionalSchool[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Escuelas', href: adminSchoolsRoutes.index().url },
]);
</script>

<template>
    <Head title="Escuelas" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Escuelas"
            subtitle="Administra escuelas por facultad y el rango de bases permitidas."
        />

        <AdminSection title="Nueva escuela">
            <Form
                v-bind="adminSchoolsRoutes.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <div class="grid gap-1">
                    <label class="text-sm" for="code">Código</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="ep_sistemas"
                        required
                    />
                    <InputError :message="errors.code" />
                </div>

                <div class="grid gap-1">
                    <label class="text-sm" for="faculty_id">Facultad</label>
                    <select
                        id="faculty_id"
                        name="faculty_id"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        required
                    >
                        <option value="" disabled selected>
                            Selecciona una facultad
                        </option>
                        <option
                            v-for="f in props.faculties"
                            :key="f.id"
                            :value="String(f.id)"
                        >
                            {{ f.name }}
                            <span v-if="!f.active"> (inactiva)</span>
                        </option>
                    </select>
                    <InputError :message="errors.faculty_id" />
                </div>

                <div class="grid gap-1">
                    <label class="text-sm" for="name">Nombre</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="E.P. Sistemas"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-1">
                    <label class="text-sm" for="base_year_min"
                        >Base mínima (año)</label
                    >
                    <input
                        id="base_year_min"
                        name="base_year_min"
                        type="number"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="2018"
                        required
                    />
                    <InputError :message="errors.base_year_min" />
                </div>

                <div class="grid gap-1">
                    <label class="text-sm" for="base_year_max"
                        >Base máxima (año)</label
                    >
                    <input
                        id="base_year_max"
                        name="base_year_max"
                        type="number"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="2026"
                        required
                    />
                    <InputError :message="errors.base_year_max" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="active" value="0" />
                    <input
                        id="active"
                        type="checkbox"
                        name="active"
                        value="1"
                        checked
                    />
                    <label class="text-sm" for="active">Activa</label>
                </div>

                <div class="flex items-center justify-end">
                    <Button type="submit" :disabled="processing">
                        Crear
                    </Button>
                </div>
            </Form>
        </AdminSection>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Facultad</TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Escuela</TableHead>
                    <TableHead>Rango de bases</TableHead>
                    <TableHead>Activa</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="s in props.schools" :key="s.id">
                    <TableCell class="align-top">
                        <Form
                            v-bind="adminSchoolsRoutes.update.form(s.id)"
                            v-slot="{ errors, processing }"
                            class="grid gap-2 md:grid-cols-[220px_150px_1fr_200px_auto]"
                        >
                            <select
                                name="faculty_id"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                :value="String(s.faculty_id)"
                                required
                            >
                                <option
                                    v-for="f in props.faculties"
                                    :key="f.id"
                                    :value="String(f.id)"
                                >
                                    {{ f.name }}
                                </option>
                            </select>

                            <div class="grid gap-1">
                                <input
                                    name="code"
                                    type="text"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    :value="s.code ?? ''"
                                    placeholder="ep_sistemas"
                                    required
                                />
                                <div
                                    v-if="errors.code"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.code }}
                                </div>
                            </div>

                            <div class="grid gap-1">
                                <input
                                    name="name"
                                    type="text"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    :value="s.name"
                                    required
                                />
                                <div
                                    v-if="errors.name"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.name }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    name="base_year_min"
                                    type="number"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    :value="String(s.base_year_min)"
                                    required
                                />
                                <input
                                    name="base_year_max"
                                    type="number"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    :value="String(s.base_year_max)"
                                    required
                                />
                            </div>

                            <div class="flex items-center justify-end gap-2">
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
                                        :checked="s.active"
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

                            <div
                                v-if="
                                    errors.faculty_id ||
                                    errors.code ||
                                    errors.base_year_min ||
                                    errors.base_year_max
                                "
                                class="text-xs text-destructive md:col-span-5"
                            >
                                <div v-if="errors.faculty_id">
                                    {{ errors.faculty_id }}
                                </div>
                                <div v-if="errors.code">
                                    {{ errors.code }}
                                </div>
                                <div v-if="errors.base_year_min">
                                    {{ errors.base_year_min }}
                                </div>
                                <div v-if="errors.base_year_max">
                                    {{ errors.base_year_max }}
                                </div>
                            </div>
                        </Form>
                    </TableCell>

                    <TableCell class="align-top">
                        <div class="text-sm font-medium">
                            {{ s.code ?? '—' }}
                        </div>
                    </TableCell>

                    <TableCell class="align-top">
                        <div class="text-sm font-medium">
                            {{ s.name }}
                        </div>
                    </TableCell>

                    <TableCell class="align-top text-xs text-muted-foreground">
                        {{ formatBaseYear(s.base_year_min) }} —
                        {{ formatBaseYear(s.base_year_max) }}
                    </TableCell>

                    <TableCell class="align-top">
                        {{ s.active ? 'Sí' : 'No' }}
                    </TableCell>

                    <TableCell
                        class="text-right align-top text-xs text-muted-foreground"
                    >
                        ID: {{ s.id }}
                    </TableCell>
                </TableRow>

                <TableEmpty v-if="props.schools.length === 0" :colspan="6">
                    Sin registros.
                </TableEmpty>
            </TableBody>
        </Table>
    </div>
</template>
