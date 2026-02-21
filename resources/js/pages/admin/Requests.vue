<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/http';
import {
    approve,
    reject,
    index as adminRequestsIndex,
} from '@/routes/admin/requests';
import { requests as adminRequests } from '@/routes/api/admin';
import { type BreadcrumbItem } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    first_name?: string | null;
    last_name?: string | null;
    phone?: string | null;
    professional_school?: string | null;
    base?: string | null;
};

type Reservation = {
    id: number;
    starts_at: string;
    ends_at: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    user: User;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Solicitudes', href: adminRequestsIndex().url },
];

const queryClient = useQueryClient();

const query = useQuery({
    queryKey: ['admin-requests'],
    queryFn: () =>
        fetchJson<{ data: Reservation[] }>(adminRequests.url()).then(
            (r) => r.data,
        ),
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

const decide = (action: 'approve' | 'reject', reservationId: number): void => {
    const reason = prompt('Motivo (opcional):') ?? undefined;

    const route =
        action === 'approve' ? approve(reservationId) : reject(reservationId);

    router.post(
        route.url,
        { reason: reason && reason.trim() !== '' ? reason : null },
        {
            onSuccess: () => {
                queryClient.invalidateQueries({ queryKey: ['admin-requests'] });
            },
        },
    );
};
</script>

<template>
    <Head title="Solicitudes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Solicitudes pendientes</h1>
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
                No se pudieron cargar las solicitudes.
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="r in query.data ?? []"
                    :key="r.id"
                    class="rounded-lg border border-border/60 p-4"
                >
                    <div
                        class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="text-sm font-medium">
                                {{ r.user.name }} — {{ r.user.email }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ r.user.professional_school }} /
                                {{ r.user.base }}
                                <span v-if="r.user.phone">
                                    · {{ r.user.phone }}</span
                                >
                            </div>
                            <div class="text-sm">
                                {{ formatDateTime(r.starts_at) }} —
                                {{ formatDateTime(r.ends_at) }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-medium text-white"
                                @click="decide('approve', r.id)"
                            >
                                Aprobar
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-border/60 px-3 py-2 text-xs font-medium"
                                @click="decide('reject', r.id)"
                            >
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="(query.data ?? []).length === 0"
                    class="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground"
                >
                    No hay solicitudes pendientes.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
