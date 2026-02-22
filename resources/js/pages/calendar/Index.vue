<script setup lang="ts">
import type { CalendarOptions, EventInput, EventSourceFuncArg } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/vue3';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/http';
import { availability } from '@/routes/api/public';
import { create as createReservation } from '@/routes/reservations';

defineOptions({ layout: AppLayout });

type CalendarEvent = EventInput & {
    extendedProps?: {
        type: 'reservation' | 'blackout';
    };
};

const props = defineProps<{
    timezone: string;
}>();

const isLoading = ref(false);
const loadError = ref(false);

const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: esLocale,
    timeZone: props.timezone,
    height: 'auto',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: {
        today: 'Hoy',
    },
    displayEventTime: false,
    fixedWeekCount: false,
    dayMaxEvents: true,
    dateClick: (info) => {
        router.visit(
            createReservation.url({
                query: { date: info.dateStr },
            }),
        );
    },
    events: async (
        info: EventSourceFuncArg,
        success: (events: EventInput[]) => void,
        failure: (error: Error) => void,
    ) => {
        isLoading.value = true;
        loadError.value = false;

        try {
            const events = await fetchJson<CalendarEvent[]>(
                availability.url({
                    query: { start: info.startStr, end: info.endStr },
                }),
            );

            success(events);
        } catch (error) {
            loadError.value = true;
            failure(error);
        } finally {
            isLoading.value = false;
        }
    },
}));
</script>

<template>
    <Head title="Calendario" />

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-lg font-semibold">Calendario</h1>
            <p class="text-sm text-muted-foreground">
                Disponibilidad y reservas del mes.
            </p>
        </div>

        <div
            class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
        >
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span>
                Ocupado
            </div>
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-sm bg-slate-500"></span>
                Bloqueado
            </div>
            <div class="ml-auto text-xs">
                Zona horaria: {{ props.timezone }}
            </div>
        </div>

        <div
            v-if="loadError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudo cargar el calendario.
        </div>

        <div class="calendar rounded-lg border border-border/60 p-2">
            <div
                v-if="isLoading"
                class="px-2 pb-2 text-sm text-muted-foreground"
            >
                Cargando…
            </div>

            <FullCalendar :options="calendarOptions" />
        </div>
    </div>
</template>

<style scoped>
.calendar :deep(.fc) {
    --fc-page-bg-color: transparent;
    --fc-border-color: color-mix(in oklab, var(--border) 60%, transparent);
    --fc-neutral-bg-color: color-mix(in oklab, var(--muted) 60%, transparent);
    --fc-today-bg-color: color-mix(in oklab, var(--accent) 55%, transparent);
    --fc-event-border-color: transparent;
    color: var(--foreground);
}

.calendar :deep(.fc .fc-button) {
    border-radius: 0.375rem;
}
</style>
