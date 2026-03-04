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
import { useEventTooltip } from '@/composables/useEventTooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { APP_TIMEZONE } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import publicApiRoutes from '@/routes/api/public';
import reservationsRoutes from '@/routes/reservations';

defineOptions({ layout: AppLayout });

type CalendarEvent = EventInput & {
    extendedProps?: {
        type: 'reservation' | 'blackout' | 'pending';
    };
};

const isLoading = ref(false);
const loadError = ref(false);
const { tooltip, showTooltip, hideTooltip } = useEventTooltip();

const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: esLocale,
    timeZone: APP_TIMEZONE,
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
    eventClassNames: (arg) => {
        if (arg.event.extendedProps?.type === 'pending') {
            return ['fc-event--pending'];
        }
        return [];
    },
    eventMouseEnter: showTooltip,
    eventMouseLeave: hideTooltip,
    dateClick: (info) => {
        router.visit(
            reservationsRoutes.create.url({
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
                publicApiRoutes.availability.url({
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

    <div class="flex flex-col gap-4 p-4">
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
                <span class="h-2.5 w-2.5 rounded-sm bg-warning"></span>
                Ocupado
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="h-2.5 w-2.5 rounded-sm border border-dashed border-blue-500 bg-blue-500 opacity-70"
                ></span>
                Solicitado
            </div>
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-sm bg-muted-foreground"></span>
                Bloqueado
            </div>
            <div class="ml-auto text-xs">
                Zona horaria: {{ APP_TIMEZONE }}
            </div>
        </div>

        <div
            v-if="loadError"
            class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
        >
            No se pudo cargar el calendario.
        </div>

        <Teleport to="body">
            <Transition name="tooltip-fade">
                <div
                    v-if="tooltip.visible"
                    class="event-tooltip"
                    :style="{
                        top: `${tooltip.top}px`,
                        left: `${tooltip.left}px`,
                    }"
                >
                    <span class="font-medium">{{ tooltip.label }}</span>
                    <span v-if="tooltip.time" class="text-background/70">
                        {{ tooltip.time }}
                    </span>
                </div>
            </Transition>
        </Teleport>

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

<style scoped>
.event-tooltip {
    position: absolute;
    z-index: 50;
    transform: translateX(-50%) translateY(-100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    border-radius: 0.375rem;
    background: var(--foreground);
    color: var(--background);
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    line-height: 1rem;
    pointer-events: none;
    white-space: nowrap;
}

.tooltip-fade-enter-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}

.tooltip-fade-leave-active {
    transition:
        opacity 0.1s ease,
        transform 0.1s ease;
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-100%) scale(0.95);
}
</style>
