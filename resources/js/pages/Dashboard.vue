<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarPlus,
    Clock,
    ListChecks,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatDateTime } from '@/lib/formatters';
import * as appRoutes from '@/routes';
import reservationsRoutes from '@/routes/reservations';
import type { ReservationStatus } from '@/types/admin';

type DashboardReservation = {
    id: number;
    status: ReservationStatus;
    starts_at: string;
    ends_at: string;
    created_at: string;
    updated_at?: string;
};

type DashboardBlackout = {
    starts_at: string;
    ends_at: string;
    reason?: string | null;
};

const props = defineProps<{
    upcoming_reservations: DashboardReservation[];
    active_count: number;
    max_active: number;
    weekly_quota_used: number;
    weekly_quota_max: number;
    recent_activity: DashboardReservation[];
    upcoming_blackouts: DashboardBlackout[];
    todays_opening_hours: { open: string; close: string } | null;
}>();

useBreadcrumbs([
    {
        title: 'Dashboard',
        href: appRoutes.dashboard().url,
    },
]);

const activePercent = computed(() => {
    if (props.max_active <= 0) {
        return 0;
    }

    return Math.min(100, (props.active_count / props.max_active) * 100);
});

const quotaPercent = computed(() => {
    if (props.weekly_quota_max <= 0) {
        return 0;
    }

    return Math.min(
        100,
        (props.weekly_quota_used / props.weekly_quota_max) * 100,
    );
});

const activityStatusLabel: Record<ReservationStatus, string> = {
    pending: 'Solicitud creada',
    approved: 'Reserva aprobada',
    rejected: 'Solicitud rechazada',
    cancelled: 'Reserva cancelada',
};

const relativeTime = (iso: string): string => {
    const diff = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) {
        return 'Justo ahora';
    }

    if (minutes < 60) {
        return `Hace ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `Hace ${hours}h`;
    }

    const days = Math.floor(hours / 24);

    return `Hace ${days}d`;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Stat cards -->
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- Active reservations -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">
                        Reservas activas
                    </CardTitle>
                    <ListChecks class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ active_count }}
                        <span class="text-sm font-normal text-muted-foreground">
                            / {{ max_active }}
                        </span>
                    </div>
                    <div class="mt-3 h-2 w-full rounded-full bg-muted">
                        <div
                            class="h-2 rounded-full bg-primary transition-all"
                            :style="{ width: `${activePercent}%` }"
                        />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <template v-if="active_count >= max_active">
                            Límite alcanzado
                        </template>
                        <template v-else>
                            {{ max_active - active_count }} disponible{{
                                max_active - active_count !== 1 ? 's' : ''
                            }}
                        </template>
                    </p>
                </CardContent>
            </Card>

            <!-- Weekly quota -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">
                        Cuota semanal
                    </CardTitle>
                    <CalendarClock class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ weekly_quota_used }}
                        <span class="text-sm font-normal text-muted-foreground">
                            / {{ weekly_quota_max }}
                        </span>
                    </div>
                    <div class="mt-3 h-2 w-full rounded-full bg-muted">
                        <div
                            class="h-2 rounded-full bg-primary transition-all"
                            :style="{ width: `${quotaPercent}%` }"
                        />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Escuela + base esta semana
                    </p>
                </CardContent>
            </Card>

            <!-- Today's hours -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">
                        Horario hoy
                    </CardTitle>
                    <Clock class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        <template v-if="todays_opening_hours">
                            {{ todays_opening_hours.open }} –
                            {{ todays_opening_hours.close }}
                        </template>
                        <template v-else>
                            Cerrado
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <template v-if="todays_opening_hours">
                            Horario de atención
                        </template>
                        <template v-else>
                            Sin atención hoy
                        </template>
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Main content -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Upcoming reservations -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">
                        Próximas reservas
                    </CardTitle>
                    <Link
                        :href="reservationsRoutes.create().url"
                        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <CalendarPlus class="h-3.5 w-3.5" />
                        Nueva solicitud
                    </Link>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="upcoming_reservations.length === 0"
                        class="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground"
                    >
                        <CalendarClock class="h-8 w-8" />
                        <p>No tienes reservas próximas.</p>
                        <Link
                            :href="reservationsRoutes.create().url"
                            class="text-primary underline underline-offset-4 hover:text-primary/80"
                        >
                            Crear una solicitud
                        </Link>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="r in upcoming_reservations"
                            :key="r.id"
                            class="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2.5"
                        >
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-medium">
                                    {{ formatDateTime(r.starts_at) }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    hasta {{ formatDateTime(r.ends_at) }}
                                </span>
                            </div>
                            <StatusBadge :status="r.status" />
                        </div>
                        <Link
                            v-if="upcoming_reservations.length > 0"
                            :href="reservationsRoutes.index().url"
                            class="block text-center text-xs text-muted-foreground hover:text-foreground"
                        >
                            Ver todas mis reservas
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent activity -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium">
                        Actividad reciente
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="recent_activity.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Sin actividad reciente.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="r in recent_activity"
                            :key="`activity-${r.id}`"
                            class="flex items-center gap-3"
                        >
                            <div
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="{
                                    'bg-amber-500': r.status === 'pending',
                                    'bg-green-500': r.status === 'approved',
                                    'bg-red-500': r.status === 'rejected',
                                    'bg-gray-400': r.status === 'cancelled',
                                }"
                            />
                            <div class="flex flex-1 items-center justify-between gap-2">
                                <div class="flex flex-col">
                                    <span class="text-sm">
                                        {{ activityStatusLabel[r.status] }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatDateTime(r.starts_at) }}
                                    </span>
                                </div>
                                <span class="shrink-0 text-xs text-muted-foreground">
                                    {{ relativeTime(r.updated_at ?? r.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Upcoming blackouts (conditional) -->
        <Card v-if="upcoming_blackouts.length > 0">
            <CardHeader class="flex flex-row items-center gap-2">
                <ShieldAlert class="h-4 w-4 text-amber-500" />
                <CardTitle class="text-sm font-medium">
                    Próximos bloqueos (7 días)
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <div
                        v-for="(b, i) in upcoming_blackouts"
                        :key="i"
                        class="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800/40 dark:bg-amber-950/20"
                    >
                        <div class="flex flex-col gap-0.5">
                            <span class="text-sm font-medium">
                                {{ formatDateTime(b.starts_at) }} –
                                {{ formatDateTime(b.ends_at) }}
                            </span>
                            <span
                                v-if="b.reason"
                                class="text-xs text-muted-foreground"
                            >
                                {{ b.reason }}
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
