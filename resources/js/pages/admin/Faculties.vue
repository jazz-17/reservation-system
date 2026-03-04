<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatBaseYear } from '@/lib/formatters';
import adminFacultiesRoutes from '@/routes/admin/faculties';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminSchoolsRoutes from '@/routes/admin/schools';
import type { Faculty, ProfessionalSchool } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
    schools: ProfessionalSchool[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Facultades y Escuelas', href: adminFacultiesRoutes.index().url },
]);
</script>

<template>
    <Head title="Facultades y Escuelas" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Facultades y Escuelas"
            subtitle="Administra las facultades y escuelas profesionales."
        />

        <Tabs default-value="facultades">
            <TabsList>
                <TabsTrigger value="facultades">Facultades</TabsTrigger>
                <TabsTrigger value="escuelas">Escuelas</TabsTrigger>
            </TabsList>

            <TabsContent value="facultades" class="flex flex-col gap-4">
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
                        <TableRow
                            v-for="f in props.faculties"
                            :key="f.id"
                        >
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

                        <TableEmpty
                            v-if="props.faculties.length === 0"
                            :colspan="3"
                        >
                            Sin registros.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </TabsContent>

            <TabsContent value="escuelas" class="flex flex-col gap-4">
                <AdminSection title="Nueva escuela">
                    <Form
                        v-bind="adminSchoolsRoutes.store.form()"
                        v-slot="{ errors, processing }"
                        class="mt-4 grid gap-3 md:grid-cols-2"
                    >
                        <div class="grid gap-1">
                            <Label for="code">Código</Label>
                            <Input
                                id="code"
                                name="code"
                                type="text"
                                placeholder="ep_sistemas"
                                required
                            />
                            <InputError :message="errors.code" />
                        </div>

                        <div class="grid gap-1">
                            <Label for="faculty_id">Facultad</Label>
                            <NativeSelect id="faculty_id" name="faculty_id" required>
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
                            </NativeSelect>
                            <InputError :message="errors.faculty_id" />
                        </div>

                        <div class="grid gap-1">
                            <Label for="school_name">Nombre</Label>
                            <Input
                                id="school_name"
                                name="name"
                                type="text"
                                placeholder="E.P. Sistemas"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-1">
                            <Label for="base_year_min">Base mínima (año)</Label>
                            <Input
                                id="base_year_min"
                                name="base_year_min"
                                type="number"
                                placeholder="2018"
                                required
                            />
                            <InputError :message="errors.base_year_min" />
                        </div>

                        <div class="grid gap-1">
                            <Label for="base_year_max">Base máxima (año)</Label>
                            <Input
                                id="base_year_max"
                                name="base_year_max"
                                type="number"
                                placeholder="2026"
                                required
                            />
                            <InputError :message="errors.base_year_max" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="active" value="0" />
                            <input
                                id="school_active"
                                type="checkbox"
                                name="active"
                                value="1"
                                checked
                            />
                            <Label for="school_active">Activa</Label>
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
                                    <NativeSelect
                                        name="faculty_id"
                                        :default-value="String(s.faculty_id)"
                                        required
                                    >
                                        <option
                                            v-for="f in props.faculties"
                                            :key="f.id"
                                            :value="String(f.id)"
                                        >
                                            {{ f.name }}
                                        </option>
                                    </NativeSelect>

                                    <div class="grid gap-1">
                                        <Input
                                            name="code"
                                            type="text"
                                            :default-value="s.code ?? ''"
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
                                        <Input
                                            name="name"
                                            type="text"
                                            :default-value="s.name"
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
                                        <Input
                                            name="base_year_min"
                                            type="number"
                                            :default-value="String(s.base_year_min)"
                                            required
                                        />
                                        <Input
                                            name="base_year_max"
                                            type="number"
                                            :default-value="String(s.base_year_max)"
                                            required
                                        />
                                    </div>

                                    <div class="flex items-center justify-end gap-2">
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
                                                :checked="s.active"
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

                            <TableCell class="text-right align-top text-xs text-muted-foreground">
                                ID: {{ s.id }}
                            </TableCell>
                        </TableRow>

                        <TableEmpty
                            v-if="props.schools.length === 0"
                            :colspan="6"
                        >
                            Sin registros.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </TabsContent>
        </Tabs>
    </div>
</template>
