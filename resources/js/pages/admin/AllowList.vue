<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import {
    index as allowListIndex,
    importMethod as importAllowList,
} from '@/routes/admin/allow-list';
import type { ImportReport } from '@/types/admin';

const props = defineProps<{
    count: number;
}>();

const page = usePage();

const report = (page.props.flash?.import_report ?? null) as ImportReport | null;

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Allow-list', href: allowListIndex().url },
]);
</script>

<template>
    <Head title="Allow-list" />

    <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Allow-list</h1>
                <p class="text-sm text-muted-foreground">
                    Correos permitidos para registrarse. Total:
                    {{ props.count }}
                </p>
            </div>

            <div v-if="report" class="rounded-lg border border-border/60 p-4">
                <div class="text-sm font-medium">Última importación</div>
                <div class="mt-2 text-sm text-muted-foreground">
                    Importados: {{ report.imported }} · Duplicados:
                    {{ report.duplicates }} · Inválidos: {{ report.invalid }}
                </div>
                <div v-if="report.invalid_rows?.length" class="mt-3">
                    <div class="text-sm font-medium">Filas inválidas</div>
                    <ul
                        class="mt-2 list-disc pl-5 text-sm text-muted-foreground"
                    >
                        <li
                            v-for="row in report.invalid_rows.slice(0, 10)"
                            :key="row.row"
                        >
                            Fila {{ row.row }}: {{ row.value }}
                        </li>
                    </ul>
                    <div
                        v-if="report.invalid_rows.length > 10"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Se muestran 10 de {{ report.invalid_rows.length }}.
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="text-sm font-medium">Importar</div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sube un archivo CSV/XLSX con una columna (o primera columna)
                    de correos.
                </p>

                <Form
                    v-bind="importAllowList.form()"
                    enctype="multipart/form-data"
                    v-slot="{ errors, processing }"
                    class="mt-4 grid gap-3"
                >
                    <div class="grid gap-1">
                        <label class="text-sm" for="mode">Modo</label>
                        <select
                            id="mode"
                            name="mode"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="merge">Fusionar</option>
                            <option value="replace">Reemplazar</option>
                        </select>
                        <InputError :message="errors.mode" />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-sm" for="file">Archivo</label>
                        <input
                            id="file"
                            name="file"
                            type="file"
                            class="block w-full text-sm"
                            accept=".csv,.txt,.xlsx,.xls"
                        />
                        <InputError :message="errors.file" />
                    </div>

                    <div class="flex items-center justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            :disabled="processing"
                        >
                            Importar
                        </button>
                    </div>
                </Form>
            </div>
    </div>
</template>
