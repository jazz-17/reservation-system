<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed } from 'vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatDateTime } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import { reservations as studentReservations } from '@/routes/api/student';
import {
    cancel as cancelReservation,
    create as createReservation,
    index as reservationsIndex,
} from '@/routes/reservations';
import { show as reservationPdf } from '@/routes/reservations/pdf';
import type { ReservationStatus } from '@/types/admin';

type Reservation = {
    id: number;
    status: ReservationStatus;
    starts_at: string;
    ends_at: string;
    decision_reason?: string | null;
    cancellation_reason?: string | null;
};

useBreadcrumbs([{ title: 'Mis reservas', href: reservationsIndex().url }]);

const queryClient = useQueryClient();

const {
    isLoading,
    isError,
    data: reservationsData,
} = useQuery({
    queryKey: ['student-reservations'],
    queryFn: () =>
        fetchJson<{ data: Reservation[] }>(studentReservations.url()).then(
            (r) => r.data,
        ),
});

const reservations = computed<Reservation[]>(() => reservationsData.value ?? []);

const reasonLabel = (r: Reservation): string => {
    if (r.status === 'rejected') {
        return r.decision_reason?.trim() || '—';
    }

    if (r.status === 'cancelled') {
        return r.cancellation_reason?.trim() || '—';
    }

    return '—';
};

const isHistorical = (r: Reservation): boolean => {
    if (r.status === 'rejected' || r.status === 'cancelled') {
        return true;
    }

    return new Date(r.ends_at).getTime() < Date.now();
};

const activeReservations = computed(() =>
    reservations.value.filter((r) => !isHistorical(r)),
);

const historicalReservations = computed(() =>
    reservations.value.filter((r) => isHistorical(r)),
);

const cancel = (reservationId: number): void => {
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
                v-if="isLoading"
                class="overflow-hidden rounded-lg border border-border/60"
            >
                <div class="border-b border-border/60 px-4 py-3">
                    <Skeleton class="h-4 w-28" />
                </div>
                <div
                    v-for="i in 3"
                    :key="i"
                    class="flex items-center gap-4 border-t border-border/60 px-4 py-3 first:border-t-0"
                >
                    <Skeleton class="h-4 w-20" />
                    <Skeleton class="h-4 w-36" />
                    <Skeleton class="h-4 w-36" />
                    <Skeleton class="ml-auto h-7 w-16" />
                </div>
                <div class="border-y border-border/60 px-4 py-3">
                    <Skeleton class="h-4 w-16" />
                </div>
                <div
                    v-for="i in 2"
                    :key="i"
                    class="flex items-center gap-4 border-t border-border/60 px-4 py-3 first:border-t-0"
                >
                    <Skeleton class="h-4 w-20" />
                    <Skeleton class="h-4 w-36" />
                    <Skeleton class="h-4 w-36" />
                </div>
            </div>

            <div
                v-else-if="isError"
                class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
            >
                No se pudieron cargar tus reservas.
            </div>

            <div
                v-else
                class="overflow-hidden rounded-lg border border-border/60"
            >
                <div class="border-b border-border/60 px-4 py-3 text-sm font-medium">
                    Reservas activas
                </div>
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
                            v-for="r in activeReservations"
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
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a
                                        v-if="r.status === 'approved'"
                                        :href="reservationPdf(r.id).url"
                                        class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Descargar PDF
                                    </a>
                                    <ConfirmDialog
                                        v-if="
                                            r.status === 'pending' ||
                                            r.status === 'approved'
                                        "
                                        title="¿Cancelar esta reserva?"
                                        description="Esta acción no se puede deshacer."
                                        confirm-label="Cancelar reserva"
                                        variant="destructive"
                                        @confirm="cancel(r.id)"
                                    >
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                            >
                                                Cancelar
                                            </button>
                                        </template>
                                    </ConfirmDialog>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="activeReservations.length === 0">
                            <td
                                class="px-4 py-8 text-center text-muted-foreground"
                                colspan="4"
                            >
                                No tienes reservas activas.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="border-y border-border/60 px-4 py-3 text-sm font-medium">
                    Historial
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Inicio</th>
                            <th class="px-4 py-3">Fin</th>
                            <th class="px-4 py-3">Motivo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in historicalReservations"
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
                                {{ reasonLabel(r) }}
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
                        <tr v-if="historicalReservations.length === 0">
                            <td
                                class="px-4 py-8 text-center text-muted-foreground"
                                colspan="5"
                            >
                                Sin historial.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </div>
</template>
