<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { formatBaseYear, formatDateTime } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import { index as historyIndex } from '@/routes/admin/history';
import { history as adminHistory } from '@/routes/api/admin';
import { show as reservationPdf } from '@/routes/reservations/pdf';
import type { AdminReservation } from '@/types/admin';

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Historial', href: historyIndex().url },
]);

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

const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-history', queryOptions],
    queryFn: () =>
        fetchJson<{ data: AdminReservation[] }>(
            adminHistory.url({ query: queryOptions.value }),
        ).then((r) => r.data),
});
</script>

<template>
    <Head title="Historial" />

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
            v-if="isLoading"
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
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="i in 5"
                        :key="i"
                        class="border-t border-border/60"
                    >
                        <td class="px-4 py-3"><Skeleton class="h-4 w-16" /></td>
                        <td class="px-4 py-3"><Skeleton class="h-4 w-28" /></td>
                        <td class="px-4 py-3"><Skeleton class="h-4 w-28" /></td>
                        <td class="px-4 py-3">
                            <Skeleton class="mb-1 h-4 w-32" />
                            <Skeleton class="h-3 w-40" />
                        </td>
                        <td class="px-4 py-3"><Skeleton class="h-4 w-24" /></td>
                        <td class="px-4 py-3 text-right">
                            <Skeleton class="ml-auto h-7 w-10" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else-if="isError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudo cargar el historial.
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-border/60">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Inicio</th>
                        <th class="px-4 py-3">Fin</th>
                        <th class="px-4 py-3">Estudiante</th>
                        <th class="px-4 py-3">Escuela/Base</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in data ?? []"
                        :key="r.id"
                        class="border-t border-border/60"
                    >
                        <td class="px-4 py-3">
                            <StatusBadge :status="r.status" />
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
                            {{ r.user.professional_school?.name ?? '—' }} /
                            {{ formatBaseYear(r.user.base_year) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                v-if="r.status === 'approved'"
                                :href="reservationPdf(r.id).url"
                                class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                target="_blank"
                                rel="noopener"
                            >
                                PDF
                            </a>
                        </td>
                    </tr>
                    <tr v-if="(data ?? []).length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
