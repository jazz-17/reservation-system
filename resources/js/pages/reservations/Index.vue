<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed } from 'vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
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

            <div v-if="isLoading" class="flex flex-col gap-3">
                <h2 class="text-sm font-medium">Reservas activas</h2>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Estado</TableHead>
                            <TableHead>Inicio</TableHead>
                            <TableHead>Fin</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="i in 3" :key="i">
                            <TableCell><Skeleton class="h-4 w-20" /></TableCell>
                            <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                            <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                            <TableCell class="text-right"><Skeleton class="ml-auto h-7 w-16" /></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <h2 class="text-sm font-medium">Historial</h2>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Estado</TableHead>
                            <TableHead>Inicio</TableHead>
                            <TableHead>Fin</TableHead>
                            <TableHead>Motivo</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="i in 2" :key="i">
                            <TableCell><Skeleton class="h-4 w-20" /></TableCell>
                            <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                            <TableCell><Skeleton class="h-4 w-36" /></TableCell>
                            <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div
                v-else-if="isError"
                class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
            >
                No se pudieron cargar tus reservas.
            </div>

            <div v-else class="flex flex-col gap-3">
                <h2 class="text-sm font-medium">Reservas activas</h2>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Estado</TableHead>
                            <TableHead>Inicio</TableHead>
                            <TableHead>Fin</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="r in activeReservations"
                            :key="r.id"
                        >
                            <TableCell>
                                <StatusBadge :status="r.status" />
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(r.starts_at) }}
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(r.ends_at) }}
                            </TableCell>
                            <TableCell class="text-right">
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
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                            >
                                                Cancelar
                                            </Button>
                                        </template>
                                    </ConfirmDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty v-if="activeReservations.length === 0" :colspan="4">
                            No tienes reservas activas.
                        </TableEmpty>
                    </TableBody>
                </Table>

                <h2 class="text-sm font-medium">Historial</h2>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Estado</TableHead>
                            <TableHead>Inicio</TableHead>
                            <TableHead>Fin</TableHead>
                            <TableHead>Motivo</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="r in historicalReservations"
                            :key="r.id"
                        >
                            <TableCell>
                                <StatusBadge :status="r.status" />
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(r.starts_at) }}
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(r.ends_at) }}
                            </TableCell>
                            <TableCell>
                                {{ reasonLabel(r) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <a
                                    v-if="r.status === 'approved'"
                                    :href="reservationPdf(r.id).url"
                                    class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    PDF
                                </a>
                            </TableCell>
                        </TableRow>
                        <TableEmpty v-if="historicalReservations.length === 0" :colspan="5">
                            Sin historial.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
    </div>
</template>
