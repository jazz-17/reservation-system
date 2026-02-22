<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import {
    index as facultiesIndex,
    store,
    update,
} from '@/routes/admin/faculties';
import type { Faculty } from '@/types/admin';

const props = defineProps<{
    faculties: Faculty[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Facultades', href: facultiesIndex().url },
]);
</script>

<template>
    <Head title="Facultades" />

    <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Facultades</h1>
                <p class="text-sm text-muted-foreground">
                    Administra las facultades disponibles para el registro.
                </p>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="text-sm font-medium">Nueva facultad</div>

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
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Activa</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="f in props.faculties"
                            :key="f.id"
                            class="border-t border-border/60"
                        >
                            <td class="px-4 py-3">
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
                                                :checked="f.active"
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

                                    <div class="text-xs text-destructive">
                                        <div v-if="errors.name">
                                            {{ errors.name }}
                                        </div>
                                    </div>
                                </Form>
                            </td>
                            <td class="px-4 py-3">
                                {{ f.active ? 'Sí' : 'No' }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                ID: {{ f.id }}
                            </td>
                        </tr>

                        <tr v-if="props.faculties.length === 0">
                            <td
                                colspan="3"
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

