<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatBaseYear, formatDateTime } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import {
    approve,
    reject,
    index as adminRequestsIndex,
} from '@/routes/admin/requests';
import { requests as adminRequests } from '@/routes/api/admin';
import type { AdminReservation } from '@/types/admin';

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Solicitudes', href: adminRequestsIndex().url },
]);

const queryClient = useQueryClient();

const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-requests'],
    queryFn: () =>
        fetchJson<{ data: AdminReservation[] }>(adminRequests.url()).then(
            (r) => r.data,
        ),
});

const decide = (action: 'approve' | 'reject', reservationId: number, reason?: string): void => {
    const route =
        action === 'approve' ? approve(reservationId) : reject(reservationId);

    router.post(
        route.url,
        { reason: reason ?? null },
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

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader title="Solicitudes pendientes" />

        <div v-if="isLoading" class="grid gap-3">
            <div
                v-for="i in 3"
                :key="i"
                class="rounded-lg border border-border/60 p-4"
            >
                <div
                    class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                >
                    <div class="space-y-2">
                        <Skeleton class="h-4 w-48" />
                        <Skeleton class="h-3 w-64" />
                        <Skeleton class="h-4 w-40" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Skeleton class="h-9 w-20 rounded-md" />
                        <Skeleton class="h-9 w-20 rounded-md" />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else-if="isError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudieron cargar las solicitudes.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="r in data ?? []"
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
                            {{ r.user.professional_school?.name ?? '—' }} /
                            {{ formatBaseYear(r.user.base_year) }}
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
                        <ConfirmDialog
                            title="Aprobar solicitud"
                            confirm-label="Aprobar"
                            input-label="Motivo (opcional)"
                            @confirm="(reason) => decide('approve', r.id, reason)"
                        >
                            <template #trigger>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="bg-emerald-600 text-white hover:bg-emerald-600/90"
                                >
                                    Aprobar
                                </Button>
                            </template>
                        </ConfirmDialog>
                        <ConfirmDialog
                            title="Rechazar solicitud"
                            confirm-label="Rechazar"
                            variant="destructive"
                            input-label="Motivo (opcional)"
                            @confirm="(reason) => decide('reject', r.id, reason)"
                        >
                            <template #trigger>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                >
                                    Rechazar
                                </Button>
                            </template>
                        </ConfirmDialog>
                    </div>
                </div>
            </div>

            <div
                v-if="(data ?? []).length === 0"
                class="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground"
            >
                No hay solicitudes pendientes.
            </div>
        </div>
    </div>
</template>
