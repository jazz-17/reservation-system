<script setup lang="ts">
import type {
    CalendarOptions,
    EventInput,
    EventSourceFuncArg,
} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppCalendar from '@/components/AppCalendar.vue';
import { Skeleton } from '@/components/ui/skeleton';
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
            failure(
                error instanceof Error
                    ? error
                    : new Error('No se pudo cargar el calendario.'),
            );
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

        <AppCalendar :options="calendarOptions" clickable :loading="isLoading">
            <template #loading>
                <div class="flex h-full flex-col gap-0 bg-card">
                    <!-- Toolbar -->
                    <div class="flex items-center justify-between px-1 py-3">
                        <div class="flex gap-1">
                            <Skeleton class="h-8 w-9 rounded-md" />
                            <Skeleton class="h-8 w-9 rounded-md" />
                            <Skeleton class="ml-1 h-8 w-12 rounded-md" />
                        </div>
                        <Skeleton class="h-7 w-44 rounded-md" />
                        <div class="w-24" />
                    </div>

                    <!-- Day headers -->
                    <div class="grid grid-cols-7 border-t border-border/60">
                        <Skeleton
                            v-for="i in 7"
                            :key="i"
                            class="mx-auto my-2 h-4 w-8 rounded"
                        />
                    </div>

                    <!-- Week rows -->
                    <div
                        v-for="row in 5"
                        :key="row"
                        class="grid flex-1 grid-cols-7 border-t border-border/60"
                    >
                        <div
                            v-for="col in 7"
                            :key="col"
                            class="border-r border-border/60 p-1.5 last:border-r-0"
                        >
                            <Skeleton class="h-4 w-5 rounded" />
                        </div>
                    </div>
                </div>
            </template>
        </AppCalendar>
    </div>
</template>
