<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/http';
import { reservations as studentReservations } from '@/routes/api/student';
import {
    cancel as cancelReservation,
    create as createReservation,
    index as reservationsIndex,
} from '@/routes/reservations';
import { type BreadcrumbItem } from '@/types';

type Reservation = {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    starts_at: string;
    ends_at: string;
    decision_reason?: string | null;
    cancellation_reason?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis reservas', href: reservationsIndex().url },
];

const queryClient = useQueryClient();

const query = useQuery({
    queryKey: ['student-reservations'],
    queryFn: () =>
        fetchJson<{ data: Reservation[] }>(studentReservations.url()).then(
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

const statusLabel = (status: Reservation['status']): string => {
    switch (status) {
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

const cancel = (reservationId: number): void => {
    if (!confirm('¿Cancelar esta reserva?')) {
        return;
    }

    router.post(
        cancelReservation(reservationId).url,
        {},
        {
            onSuccess: () => {
                queryClient.invalidateQueries({
                    queryKey: ['student-reservations'],
                });
            },
        },
    );
};
</script>

<template>
    <Head title="Mis reservas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-lg font-semibold">Mis reservas</h1>
                <Link
                    :href="createReservation().url"
                    class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
                >
                    Nueva solicitud
                </Link>
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
                No se pudieron cargar tus reservas.
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
                            <th class="px-4 py-3"></th>
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
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="
                                        r.status === 'pending' ||
                                        r.status === 'approved'
                                    "
                                    type="button"
                                    class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                    @click="cancel(r.id)"
                                >
                                    Cancelar
                                </button>
                            </td>
                        </tr>
                        <tr v-if="(query.data ?? []).length === 0">
                            <td
                                class="px-4 py-8 text-center text-muted-foreground"
                                colspan="4"
                            >
                                Aún no tienes reservas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
