<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatBaseYear } from '@/lib/formatters';
import { index as schoolsIndex, store, update } from '@/routes/admin/schools';
import type { Faculty, ProfessionalSchool } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
    schools: ProfessionalSchool[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Escuelas', href: schoolsIndex().url },
]);
</script>

<template>
    <Head title="Escuelas" />

    <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Escuelas</h1>
                <p class="text-sm text-muted-foreground">
                    Administra escuelas por facultad y el rango de bases
                    permitidas.
                </p>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="text-sm font-medium">Nueva escuela</div>

                <Form
                    v-bind="store.form()"
                    v-slot="{ errors, processing }"
                    class="mt-4 grid gap-3 md:grid-cols-2"
                >
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
                        <button
                            type="submit"
                            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            :disabled="processing"
                        >
                            Crear
                        </button>
                    </div>
                </Form>
            </div>

            <div class="overflow-hidden rounded-lg border border-border/60">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Facultad</th>
                            <th class="px-4 py-3">Escuela</th>
                            <th class="px-4 py-3">Rango de bases</th>
                            <th class="px-4 py-3">Activa</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in props.schools"
                            :key="s.id"
                            class="border-t border-border/60"
                        >
                            <td class="px-4 py-3 align-top">
                                <Form
                                    v-bind="update.form(s.id)"
                                    v-slot="{ errors, processing }"
                                    class="grid gap-2 md:grid-cols-[220px_1fr_200px_auto]"
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
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
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

                                        <button
                                            type="submit"
                                            class="rounded-md border border-border/60 px-3 py-2 text-xs font-medium disabled:opacity-50"
                                            :disabled="processing"
                                        >
                                            Guardar
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            errors.faculty_id ||
                                            errors.base_year_min ||
                                            errors.base_year_max
                                        "
                                        class="md:col-span-4 text-xs text-destructive"
                                    >
                                        <div v-if="errors.faculty_id">
                                            {{ errors.faculty_id }}
                                        </div>
                                        <div v-if="errors.base_year_min">
                                            {{ errors.base_year_min }}
                                        </div>
                                        <div v-if="errors.base_year_max">
                                            {{ errors.base_year_max }}
                                        </div>
                                    </div>
                                </Form>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="text-sm font-medium">
                                    {{ s.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    ID: {{ s.id }}
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top text-xs text-muted-foreground">
                                {{ formatBaseYear(s.base_year_min) }} —
                                {{ formatBaseYear(s.base_year_max) }}
                            </td>

                            <td class="px-4 py-3 align-top">
                                {{ s.active ? 'Sí' : 'No' }}
                            </td>

                            <td class="px-4 py-3 align-top text-right text-xs text-muted-foreground">
                                {{ s.faculty?.name ?? '—' }}
                            </td>
                        </tr>

                        <tr v-if="props.schools.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Sin registros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </div>
</template>

