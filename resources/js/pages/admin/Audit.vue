<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as auditIndex } from '@/routes/admin/audit';
import { type BreadcrumbItem } from '@/types';

type Actor = {
    id: number;
    name: string;
};

type AuditEvent = {
    id: number;
    event_type: string;
    actor_id: number | null;
    actor?: Actor | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

const props = defineProps<{
    events: AuditEvent[];
    eventTypes: string[];
    filters: { event_type: string | null; from: string | null; to: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Auditoría', href: auditIndex().url },
];

const eventType = ref(props.filters.event_type ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const applyFilters = (): void => {
    const query: Record<string, string> = {};

    if (eventType.value) {
        query.event_type = eventType.value;
    }

    if (from.value) {
        query.from = from.value;
    }

    if (to.value) {
        query.to = to.value;
    }

    router.get(auditIndex().url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = (): void => {
    eventType.value = '';
    from.value = '';
    to.value = '';

    applyFilters();
};

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

const subjectLabel = (e: AuditEvent): string => {
    if (!e.subject_type || !e.subject_id) {
        return '—';
    }

    const basename = e.subject_type.split('\\').pop() ?? e.subject_type;

    return `${basename}#${e.subject_id}`;
};
</script>

<template>
    <Head title="Auditoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Auditoría</h1>
                <p class="text-sm text-muted-foreground">
                    Filtra por tipo de evento y rango de fechas.
                </p>
            </div>

            <form
                class="rounded-lg border border-border/60 p-4"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="grid gap-1 md:col-span-2">
                        <label class="text-sm" for="event_type"
                            >Tipo de evento</label
                        >
                        <select
                            id="event_type"
                            v-model="eventType"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="t in props.eventTypes"
                                :key="t"
                                :value="t"
                            >
                                {{ t }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm" for="from">Desde</label>
                        <input
                            id="from"
                            v-model="from"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm" for="to">Hasta</label>
                        <input
                            id="to"
                            v-model="to"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-border/60 px-3 py-2 text-sm"
                        @click="clearFilters"
                    >
                        Limpiar
                    </button>
                    <button
                        type="submit"
                        class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
                    >
                        Aplicar
                    </button>
                </div>
            </form>

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
                                <div v-if="e.actor">
                                    {{ e.actor.name }}
                                    <div
                                        class="text-xs text-muted-foreground"
                                    >
                                        #{{ e.actor.id }}
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground"
                                    >Sistema</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ subjectLabel(e) }}
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
