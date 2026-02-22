<script setup lang="ts">
import type { CalendarOptions, EventInput, EventSourceFuncArg } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/vue3';
import { Head, router } from '@inertiajs/vue3';
import { Skeleton } from '@/components/ui/skeleton';
import { computed, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { fetchJson } from '@/lib/http';
import { availability } from '@/routes/api/public';
import { create as createReservation } from '@/routes/reservations';

defineOptions({ layout: PublicLayout });

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
                Vista pública (solo ocupado / bloqueado).
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
            <Skeleton v-if="isLoading" class="h-[480px] rounded-md" />

            <div :class="{ hidden: isLoading }">
                <FullCalendar :options="calendarOptions" />
            </div>
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
    background: var(--background);
    border: 1px solid var(--border);
    color: var(--foreground);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    font-size: 0.875rem;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
    transition:
        background-color 0.15s,
        color 0.15s;
}

.calendar :deep(.fc .fc-button:hover) {
    background: var(--accent);
    color: var(--accent-foreground);
}

.calendar :deep(.fc .fc-button:active),
.calendar :deep(.fc .fc-button.fc-button-active) {
    background: var(--accent);
    color: var(--accent-foreground);
}

.calendar :deep(.fc .fc-button:disabled) {
    opacity: 0.5;
    pointer-events: none;
}

.calendar :deep(.fc .fc-button:focus) {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--ring) 50%, transparent);
}
</style>
