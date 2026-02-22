<script setup lang="ts">
import type {
    CalendarOptions,
    EventInput,
    EventSourceFuncArg,
} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Skeleton } from '@/components/ui/skeleton';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { fetchJson } from '@/lib/http';
import { availability } from '@/routes/api/public';
import {
    index as reservationsIndex,
    store as storeReservation,
} from '@/routes/reservations';

type OpeningHours = Record<
    'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun',
    { open: string; close: string }
>;

type CalendarEvent = EventInput & {
    extendedProps?: {
        type: 'reservation' | 'blackout';
    };
};

const props = defineProps<{
    timezone: string;
    opening_hours: OpeningHours | null;
    min_duration_minutes: number;
    max_duration_minutes: number;
}>();

useBreadcrumbs([
    { title: 'Mis reservas', href: reservationsIndex().url },
    { title: 'Nueva solicitud', href: '/reservas/nueva' },
]);

const page = usePage();

const dateFromQuery = computed((): string | null => {
    const url = new URL(page.url, window.location.origin);
    const value = url.searchParams.get('date');

    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    return value;
});

const toDateInput = (date: Date): string =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: props.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);

const selectedDate = ref<string>(
    dateFromQuery.value ?? toDateInput(new Date()),
);
const initialCalendarDate = selectedDate.value;
const startTime = ref<string>('');
const endTime = ref<string>('');

const isLoading = ref(false);
const loadError = ref(false);
const isSyncingFromCalendar = ref(false);
const isSyncingFromInput = ref(false);

const openingHours = computed<OpeningHours>(() => {
    const fallback: OpeningHours = {
        mon: { open: '08:00', close: '22:00' },
        tue: { open: '08:00', close: '22:00' },
        wed: { open: '08:00', close: '22:00' },
        thu: { open: '08:00', close: '22:00' },
        fri: { open: '08:00', close: '22:00' },
        sat: { open: '08:00', close: '22:00' },
        sun: { open: '08:00', close: '22:00' },
    };

    return props.opening_hours
        ? { ...fallback, ...props.opening_hours }
        : fallback;
});

const timeToMinutes = (value: string): number | null => {
    const match = value.match(/^(\d{2}):(\d{2})$/);
    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    return hours * 60 + minutes;
};

const minutesToDurationLabel = (minutesTotal: number): string => {
    const minutes = Math.max(0, Math.floor(minutesTotal));
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours > 0 && remainingMinutes > 0) {
        return `${hours}h ${remainingMinutes}m`;
    }

    if (hours > 0) {
        return `${hours}h`;
    }

    return `${remainingMinutes}m`;
};

const durationMinutes = computed((): number | null => {
    const startMinutes = timeToMinutes(startTime.value);
    const endMinutes = timeToMinutes(endTime.value);

    if (
        startMinutes === null ||
        endMinutes === null ||
        endMinutes <= startMinutes
    ) {
        return null;
    }

    return endMinutes - startMinutes;
});

const startsAtValue = computed((): string => {
    if (!selectedDate.value || !startTime.value) {
        return '';
    }

    return `${selectedDate.value} ${startTime.value}`;
});

const endsAtValue = computed((): string => {
    if (!selectedDate.value || !endTime.value) {
        return '';
    }

    return `${selectedDate.value} ${endTime.value}`;
});

const canSubmit = computed(() => {
    if (!startsAtValue.value || !endsAtValue.value) {
        return false;
    }

    return durationMinutes.value !== null;
});

const dayToDaysOfWeek: Record<keyof OpeningHours, number> = {
    mon: 1,
    tue: 2,
    wed: 3,
    thu: 4,
    fri: 5,
    sat: 6,
    sun: 0,
};

const businessHours = computed(() => {
    return (Object.keys(dayToDaysOfWeek) as Array<keyof OpeningHours>).map(
        (day) => ({
            daysOfWeek: [dayToDaysOfWeek[day]],
            startTime: openingHours.value[day].open,
            endTime: openingHours.value[day].close,
        }),
    );
});

const slotRange = computed((): { min: string; max: string } => {
    const opens = Object.values(openingHours.value)
        .map((d) => timeToMinutes(d.open))
        .filter((v): v is number => v !== null);

    const closes = Object.values(openingHours.value)
        .map((d) => timeToMinutes(d.close))
        .filter((v): v is number => v !== null);

    const min = opens.length ? Math.min(...opens) : 8 * 60;
    const max = closes.length ? Math.max(...closes) : 22 * 60;

    const toTime = (minutesTotal: number): string => {
        const minutes = Math.max(0, Math.floor(minutesTotal));
        const hh = String(Math.floor(minutes / 60)).padStart(2, '0');
        const mm = String(minutes % 60).padStart(2, '0');

        return `${hh}:${mm}:00`;
    };

    return { min: toTime(min), max: toTime(max) };
});

const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [timeGridPlugin, interactionPlugin],
    initialView: 'timeGridDay',
    locale: esLocale,
    timeZone: props.timezone,
    initialDate: initialCalendarDate,
    height: 560,
    nowIndicator: true,
    allDaySlot: false,
    editable: false,
    selectable: false,
    slotMinTime: slotRange.value.min,
    slotMaxTime: slotRange.value.max,
    businessHours: businessHours.value,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
    },
    buttonText: {
        today: 'Hoy',
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
    datesSet: (info) => {
        if (isSyncingFromInput.value) {
            return;
        }

        const nextDate = info.start.toISOString().slice(0, 10);
        if (nextDate !== selectedDate.value) {
            isSyncingFromCalendar.value = true;
            selectedDate.value = nextDate;
            void nextTick(() => {
                isSyncingFromCalendar.value = false;
            });
        }
    },
}));

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);

watch(selectedDate, (date) => {
    if (isSyncingFromCalendar.value) {
        return;
    }

    const api = calendarRef.value?.getApi();
    if (!api) {
        return;
    }

    isSyncingFromInput.value = true;
    api.gotoDate(date);
    void nextTick(() => {
        isSyncingFromInput.value = false;
    });
});
</script>

<template>
    <Head title="Nueva solicitud" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-lg font-semibold">Nueva solicitud</h1>
            <p class="text-sm text-muted-foreground">
                Las solicitudes se crean en estado
                <span class="font-medium">Pendiente</span>. Elige una hora de
                inicio y fin libre.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-border/60 p-4">
                <div
                    class="mb-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-amber-500"
                        ></span>
                        Ocupado
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-slate-500"
                        ></span>
                        Bloqueado
                    </div>
                    <div class="ml-auto text-xs">
                        Zona horaria: {{ props.timezone }}
                    </div>
                </div>

                <div
                    v-if="loadError"
                    class="mb-3 rounded-lg border border-border/60 p-4 text-sm text-destructive"
                >
                    No se pudo cargar la disponibilidad.
                </div>

                <div class="calendar rounded-lg border border-border/60 p-2">
                    <Skeleton v-if="isLoading" class="mx-2 mb-2 h-1" />

                    <FullCalendar
                        ref="calendarRef"
                        :options="calendarOptions"
                    />
                </div>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Crear solicitud</div>

                <Form
                    v-bind="storeReservation.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-3"
                >
                    <div class="grid gap-1">
                        <label class="text-sm" for="date">Fecha</label>
                        <input
                            id="date"
                            v-model="selectedDate"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1">
                            <label class="text-sm" for="start_time">
                                Inicio
                            </label>
                            <input
                                id="start_time"
                                v-model="startTime"
                                type="time"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="end_time"> Fin </label>
                            <input
                                id="end_time"
                                v-model="endTime"
                                type="time"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="starts_at"
                        :value="startsAtValue"
                    />
                    <input type="hidden" name="ends_at" :value="endsAtValue" />

                    <div class="rounded-md border border-border/60 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-muted-foreground">Resumen</div>
                                <div class="mt-1 font-medium">
                                    {{ selectedDate || '—' }}
                                    <span v-if="startTime">
                                        · {{ startTime }}
                                    </span>
                                    <span v-if="endTime">
                                        → {{ endTime }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-muted-foreground">
                                    Duración
                                </div>
                                <div class="mt-1 font-medium">
                                    {{
                                        durationMinutes !== null
                                            ? minutesToDurationLabel(
                                                  durationMinutes,
                                              )
                                            : '—'
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-xs text-muted-foreground">
                            Mín: {{ props.min_duration_minutes }} min · Máx:
                            {{ props.max_duration_minutes }} min
                        </div>
                    </div>

                    <InputError :message="errors.starts_at" />
                    <InputError :message="errors.ends_at" />
                    <InputError :message="errors.reservation" />

                    <button
                        type="submit"
                        class="mt-2 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                        :disabled="processing || !canSubmit"
                    >
                        Enviar solicitud
                    </button>

                    <Link
                        :href="reservationsIndex().url"
                        class="text-sm text-muted-foreground underline underline-offset-4"
                    >
                        Volver
                    </Link>
                </Form>
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
}
</style>
