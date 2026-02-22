<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import {
    index as allowListIndex,
    importMethod as importAllowList,
    template as allowListTemplate,
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
            <AdminPageHeader
                title="Allow-list"
                :subtitle="`Correos permitidos para registrarse. Total: ${props.count}`"
            />

            <AdminSection v-if="report" title="Última importación">
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
            </AdminSection>

            <AdminSection>
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-medium">Importar</div>
                    <a
                        :href="allowListTemplate().url"
                        class="rounded-md border border-border/60 px-3 py-2 text-xs font-medium"
                        download
                    >
                        Descargar plantilla CSV
                    </a>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sube un archivo CSV/XLSX con las columnas: <strong>email</strong>,
                    <strong>school_code</strong> y <strong>base</strong> (ej. B22 o 2022).
                    También funciona sin encabezados si el orden es: email, school_code, base.
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
                        <Button
                            type="submit"
                            :disabled="processing"
                        >
                            Importar
                        </Button>
                    </div>
                </Form>
            </AdminSection>
    </div>
</template>
