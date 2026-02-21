<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/http';
import { index as historyIndex } from '@/routes/admin/history';
import { history as adminHistory } from '@/routes/api/admin';
import { type BreadcrumbItem } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    professional_school?: string | null;
    base?: string | null;
};

type Reservation = {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    starts_at: string;
    ends_at: string;
    user: User;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Historial', href: historyIndex().url },
];

const status = ref<string>('');
const from = ref<string>('');
const to = ref<string>('');

const queryOptions = computed(() => {
    const query: Record<string, string> = {};
    if (status.value) query.status = status.value;
    if (from.value) query.from = from.value;
    if (to.value) query.to = to.value;
    return query;
});

const query = useQuery({
    queryKey: ['admin-history', queryOptions],
    queryFn: () =>
        fetchJson<{ data: Reservation[] }>(
            adminHistory.url({ query: queryOptions.value }),
        ).then((r) => r.data),
});

const formatDateTime = (iso: string): string => {
    const d = new Date(iso);
    return new Intl.DateTimeFormat('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
};

const statusLabel = (s: Reservation['status']): string => {
    switch (s) {
        case 'pending':
            return 'Pendiente';
        case 'approved':
            return 'Aprobada';
        case 'rejected':
            return 'Rechazada';
        case 'cancelled':
            return 'Cancelada';
    }
};
</script>

<template>
    <Head title="Historial" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Historial</h1>
                <p class="text-sm text-muted-foreground">
                    Filtra por estado y rango de fechas.
                </p>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="grid gap-1">
                        <label class="text-sm" for="status">Estado</label>
                        <select
                            id="status"
                            v-model="status"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Todos</option>
                            <option value="pending">Pendiente</option>
                            <option value="approved">Aprobada</option>
                            <option value="rejected">Rechazada</option>
                            <option value="cancelled">Cancelada</option>
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
            </div>

            <div
                v-if="query.isLoading"
                class="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground"
            >
                Cargando…
            </div>

            <div
                v-else-if="query.isError"
                class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
            >
                No se pudo cargar el historial.
            </div>

            <div
                v-else
                class="overflow-hidden rounded-lg border border-border/60"
            >
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Inicio</th>
                            <th class="px-4 py-3">Fin</th>
                            <th class="px-4 py-3">Estudiante</th>
                            <th class="px-4 py-3">Escuela/Base</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in query.data ?? []"
                            :key="r.id"
                            class="border-t border-border/60"
                        >
                            <td class="px-4 py-3">
                                {{ statusLabel(r.status) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ formatDateTime(r.starts_at) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ formatDateTime(r.ends_at) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ r.user.name }}<br />
                                <span class="text-xs text-muted-foreground">{{
                                    r.user.email
                                }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ r.user.professional_school ?? '—' }} /
                                {{ r.user.base ?? '—' }}
                            </td>
                        </tr>
                        <tr v-if="(query.data ?? []).length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Sin resultados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
