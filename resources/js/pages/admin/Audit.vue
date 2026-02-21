<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as auditIndex } from '@/routes/admin/audit';
import { type BreadcrumbItem } from '@/types';

type AuditEvent = {
    id: number;
    event_type: string;
    actor_id: number | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

const props = defineProps<{
    events: AuditEvent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Auditoría', href: auditIndex().url },
];

const formatDateTime = (iso: string): string => {
    const d = new Date(iso);
    return new Intl.DateTimeFormat('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(d);
};
</script>

<template>
    <Head title="Auditoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Auditoría</h1>
                <p class="text-sm text-muted-foreground">
                    Eventos críticos del sistema (últimos
                    {{ props.events.length }}).
                </p>
            </div>

            <div class="overflow-hidden rounded-lg border border-border/60">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Evento</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Subject</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in props.events"
                            :key="e.id"
                            class="border-t border-border/60"
                        >
                            <td class="px-4 py-3">
                                {{ formatDateTime(e.created_at) }}
                            </td>
                            <td class="px-4 py-3">{{ e.event_type }}</td>
                            <td class="px-4 py-3">
                                {{ e.actor_id ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    e.subject_type && e.subject_id
                                        ? `${e.subject_type}#${e.subject_id}`
                                        : '—'
                                }}
                            </td>
                        </tr>
                        <tr v-if="props.events.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Sin eventos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
