<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import PaginationFooter from '@/components/admin/PaginationFooter.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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
import { formatBaseYear, formatDateTime } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import adminHistoryRoutes from '@/routes/admin/history';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminApiRoutes from '@/routes/api/admin';
import reservationPdfRoutes from '@/routes/reservations/pdf';
import type { AdminReservation, SimplePaginatedResponse } from '@/types/admin';

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Historial', href: adminHistoryRoutes.index().url },
]);

const queryClient = useQueryClient();

const actionError = ref<{ reservationId: number; message: string } | null>(
    null,
);

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

const page = ref(1);

watch(queryOptions, () => {
    page.value = 1;
});

const { data: paginatedData, isLoading, isError, isPlaceholderData } = useQuery({
    queryKey: ['admin-history', queryOptions, page],
    queryFn: () =>
        fetchJson<SimplePaginatedResponse<AdminReservation>>(
            adminApiRoutes.history.url({
                query: { ...queryOptions.value, page: String(page.value) },
            }),
        ),
    placeholderData: keepPreviousData,
});

const items = computed(() => paginatedData.value?.data ?? []);
const hasNextPage = computed(() => paginatedData.value?.next_page_url != null);
const hasPrevPage = computed(() => paginatedData.value?.prev_page_url != null);

const canCancelReservation = (reservation: AdminReservation): boolean => {
    if (reservation.status !== 'pending' && reservation.status !== 'approved') {
        return false;
    }

    return new Date(reservation.ends_at).getTime() > Date.now();
};

const cancel = (reservationId: number, reason?: string): void => {
    actionError.value = null;

    router.post(
        adminHistoryRoutes.cancel(reservationId).url,
        { reason: reason ?? null },
        {
            onSuccess: () => {
                queryClient.invalidateQueries({ queryKey: ['admin-history'] });
            },
            onError: (errors: Record<string, string>) => {
                const message =
                    Object.values(errors)[0] ??
                    'No se pudo cancelar la reserva.';

                actionError.value = { reservationId, message };
            },
        },
    );
};
</script>

<template>
    <Head title="Historial" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Historial"
            subtitle="Filtra por estado y rango de fechas."
        />

        <AdminSection>
            <div class="grid gap-3 md:grid-cols-3">
                <div class="grid gap-1">
                    <Label for="status">Estado</Label>
                    <NativeSelect id="status" v-model="status">
                        <option value="">Todos</option>
                        <option value="pending">Pendiente</option>
                        <option value="approved">Aprobada</option>
                        <option value="rejected">Rechazada</option>
                        <option value="cancelled">Cancelada</option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1">
                    <Label for="from">Desde</Label>
                    <Input id="from" v-model="from" type="date" />
                </div>
                <div class="grid gap-1">
                    <Label for="to">Hasta</Label>
                    <Input id="to" v-model="to" type="date" />
                </div>
            </div>
        </AdminSection>

        <Table v-if="isLoading">
            <TableHeader>
                <TableRow>
                    <TableHead>Estado</TableHead>
                    <TableHead>Inicio</TableHead>
                    <TableHead>Fin</TableHead>
                    <TableHead>Creada</TableHead>
                    <TableHead>Estudiante</TableHead>
                    <TableHead>Escuela/Base</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="i in 5" :key="i">
                    <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-28" /></TableCell>
                    <TableCell>
                        <Skeleton class="mb-1 h-4 w-32" />
                        <Skeleton class="h-3 w-40" />
                    </TableCell>
                    <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                    <TableCell class="text-right">
                        <Skeleton class="ml-auto h-7 w-10" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div
            v-else-if="isError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudo cargar el historial.
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Estado</TableHead>
                    <TableHead>Inicio</TableHead>
                    <TableHead>Fin</TableHead>
                    <TableHead>Creada</TableHead>
                    <TableHead>Estudiante</TableHead>
                    <TableHead>Escuela/Base</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="r in items" :key="r.id">
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
                        {{ formatDateTime(r.created_at) }}
                        <template v-if="r.decided_at">
                            <br />
                            <span class="text-xs text-muted-foreground">
                                {{ r.status === 'approved' ? 'Aprobada' : 'Rechazada' }}:
                                {{ formatDateTime(r.decided_at) }}
                            </span>
                        </template>
                        <template v-else-if="r.cancelled_at">
                            <br />
                            <span class="text-xs text-muted-foreground">
                                Cancelada: {{ formatDateTime(r.cancelled_at) }}
                            </span>
                        </template>
                    </TableCell>
                    <TableCell>
                        {{ r.user.name }}<br />
                        <span class="text-xs text-muted-foreground">{{
                            r.user.email
                        }}</span>
                    </TableCell>
                    <TableCell>
                        {{ r.user.professional_school?.name ?? '—' }} /
                        {{ formatBaseYear(r.user.base_year) }}
                    </TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end gap-2">
                            <a
                                v-if="r.status === 'approved'"
                                :href="reservationPdfRoutes.show(r.id).url"
                                class="rounded-md border border-border/60 px-3 py-1.5 text-xs"
                                target="_blank"
                                rel="noopener"
                            >
                                PDF
                            </a>
                            <ConfirmDialog
                                v-if="canCancelReservation(r)"
                                title="Cancelar reserva"
                                description="La reserva pasará a estado cancelada y dejará de contar como activa."
                                confirm-label="Cancelar reserva"
                                variant="destructive"
                                input-label="Motivo (opcional)"
                                @confirm="(reason) => cancel(r.id, reason)"
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
                <TableEmpty
                    v-if="items.length === 0 && !hasPrevPage"
                    :colspan="7"
                >
                    Sin resultados.
                </TableEmpty>
            </TableBody>
        </Table>

        <Alert v-if="actionError" variant="destructive">
            <AlertDescription>
                {{ actionError.message }}
            </AlertDescription>
        </Alert>

        <PaginationFooter
            v-if="!isLoading && !isError && (items.length > 0 || hasPrevPage)"
            :current-page="paginatedData?.current_page ?? 1"
            :has-next-page="hasNextPage"
            :has-prev-page="hasPrevPage"
            :is-loading="isPlaceholderData"
            @prev="page--"
            @next="page++"
        />
    </div>
</template>
