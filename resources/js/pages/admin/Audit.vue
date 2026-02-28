<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import { Skeleton } from '@/components/ui/skeleton';
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
import { formatAuditSubject, formatDateTime } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import adminAuditRoutes from '@/routes/admin/audit';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminApiRoutes from '@/routes/api/admin';
import type { AuditEvent } from '@/types/admin';

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Auditoría', href: adminAuditRoutes.index().url },
]);

const eventType = ref<string>('');
const from = ref<string>('');
const to = ref<string>('');

const queryOptions = computed(() => {
    const query: Record<string, string> = {};
    if (eventType.value) query.event_type = eventType.value;
    if (from.value) query.from = from.value;
    if (to.value) query.to = to.value;
    return query;
});

const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-audit', queryOptions],
    queryFn: () =>
        fetchJson<{ data: AuditEvent[]; eventTypes: string[] }>(
            adminApiRoutes.audit.url({ query: queryOptions.value }),
        ),
});

const events = computed(() => data.value?.data ?? []);
const eventTypes = computed(() => data.value?.eventTypes ?? []);
</script>

<template>
    <Head title="Auditoría" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Auditoría"
            subtitle="Filtra por tipo de evento y rango de fechas."
        />

        <AdminSection>
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
                        <option v-for="t in eventTypes" :key="t" :value="t">
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
        </AdminSection>

        <Table v-if="isLoading">
            <TableHeader>
                <TableRow>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Evento</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead>Subject</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="i in 5" :key="i">
                    <TableCell><Skeleton class="h-4 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                    <TableCell>
                        <Skeleton class="mb-1 h-4 w-24" />
                        <Skeleton class="h-3 w-10" />
                    </TableCell>
                    <TableCell><Skeleton class="h-4 w-32" /></TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div
            v-else-if="isError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudo cargar la auditoría.
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Evento</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead>Subject</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="e in events" :key="e.id">
                    <TableCell>
                        {{ formatDateTime(e.created_at, { seconds: true }) }}
                    </TableCell>
                    <TableCell>{{ e.event_type }}</TableCell>
                    <TableCell>
                        <div v-if="e.actor">
                            {{ e.actor.name }}
                            <div class="text-xs text-muted-foreground">
                                #{{ e.actor.id }}
                            </div>
                        </div>
                        <span v-else class="text-muted-foreground"
                            >Sistema</span
                        >
                    </TableCell>
                    <TableCell>
                        {{ formatAuditSubject(e) }}
                    </TableCell>
                </TableRow>
                <TableEmpty v-if="events.length === 0" :colspan="4">
                    Sin eventos.
                </TableEmpty>
            </TableBody>
        </Table>
    </div>
</template>
