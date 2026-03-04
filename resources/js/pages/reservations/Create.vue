<script setup lang="ts">
import type {
    CalendarOptions,
    EventInput,
    EventSourceFuncArg,
} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import AppCalendar from '@/components/AppCalendar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { APP_TIMEZONE, formatYmdInTimeZone } from '@/lib/formatters';
import { fetchJson } from '@/lib/http';
import publicApiRoutes from '@/routes/api/public';
import reservationsRoutes from '@/routes/reservations';

type OpeningHours = Record<
    'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun',
    { open: string; close: string }
>;

type CalendarEvent = EventInput & {
    extendedProps?: {
        type: 'reservation' | 'blackout' | 'pending';
    };
};

const props = defineProps<{
    opening_hours: OpeningHours | null;
    min_duration_minutes: number;
    max_duration_minutes: number;
    lead_time_min_hours: number;
    lead_time_max_days: number;
}>();

useBreadcrumbs([
    { title: 'Mis reservas', href: reservationsRoutes.index().url },
    { title: 'Nueva solicitud', href: reservationsRoutes.create().url },
]);

const page = usePage();

const dateFromQuery = computed((): string | null => {
    const queryString = page.url.split('?')[1] ?? '';
    const value = new URLSearchParams(queryString).get('date');

    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    return value;
});

const toDateInput = (date: Date): string => formatYmdInTimeZone(date);

const selectedDate = ref<string>(
    dateFromQuery.value ?? toDateInput(new Date()),
);
const initialCalendarDate = selectedDate.value;

const dateMin = computed((): string => {
    const earliest = new Date(
        Date.now() + props.lead_time_min_hours * 60 * 60 * 1000,
    );
    return formatYmdInTimeZone(earliest);
});

const dateMax = computed((): string => {
    const latest = new Date(
        Date.now() + props.lead_time_max_days * 24 * 60 * 60 * 1000,
    );
    return formatYmdInTimeZone(latest);
});

const startTime = ref<string>('');
const endTime = ref<string>('');

const isLoading = ref(false);
const loadError = ref(false);
const loadedEvents = ref<CalendarEvent[]>([]);
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

const addMinutesToTime = (time: string, minutes: number): string | null => {
    const total = timeToMinutes(time);
    if (total === null) return null;
    const result = total + minutes;
    if (result < 0 || result >= 24 * 60) return null;
    const hh = String(Math.floor(result / 60)).padStart(2, '0');
    const mm = String(result % 60).padStart(2, '0');
    return `${hh}:${mm}`;
};

/** America/Lima is fixed UTC-5 (no DST). */
const makeDateInLima = (ymd: string, hm: string): Date | null => {
    const dateMatch = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const timeMatch = hm.match(/^(\d{2}):(\d{2})$/);
    if (!dateMatch || !timeMatch) return null;

    return new Date(
        Date.UTC(
            Number(dateMatch[1]),
            Number(dateMatch[2]) - 1,
            Number(dateMatch[3]),
            Number(timeMatch[1]) + 5,
            Number(timeMatch[2]),
        ),
    );
};

/** Day of week from YYYY-MM-DD (0=Sun … 6=Sat). Sakamoto's algorithm. */
const dayOfWeekFromYmd = (ymd: string): number | null => {
    const match = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;

    let y = Number(match[1]);
    const m = Number(match[2]);
    const d = Number(match[3]);
    const t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
    if (m < 3) y -= 1;

    return (
        (y +
            Math.floor(y / 4) -
            Math.floor(y / 100) +
            Math.floor(y / 400) +
            t[m - 1] +
            d) %
        7
    );
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

const overlap = computed(
    (): {
        hasApprovedOverlap: boolean;
        hasPendingOverlap: boolean;
        hasBlackoutOverlap: boolean;
    } | null => {
        if (!startsAtValue.value || !endsAtValue.value || !durationMinutes.value) {
            return null;
        }

        const userStart = makeDateInLima(selectedDate.value, startTime.value);
        const userEnd = makeDateInLima(selectedDate.value, endTime.value);

        if (
            userStart === null ||
            userEnd === null ||
            isNaN(userStart.getTime()) ||
            isNaN(userEnd.getTime())
        ) {
            return null;
        }

        let hasApprovedOverlap = false;
        let hasPendingOverlap = false;
        let hasBlackoutOverlap = false;

        for (const event of loadedEvents.value) {
            const type = event.extendedProps?.type;
            const eventStart = new Date(event.start as string);
            const eventEnd = new Date(event.end as string);

            if (eventStart < userEnd && eventEnd > userStart) {
                if (type === 'reservation') {
                    hasApprovedOverlap = true;
                } else if (type === 'pending') {
                    hasPendingOverlap = true;
                } else if (type === 'blackout') {
                    hasBlackoutOverlap = true;
                }
            }
        }

        if (!hasApprovedOverlap && !hasPendingOverlap && !hasBlackoutOverlap) {
            return null;
        }

        return { hasApprovedOverlap, hasPendingOverlap, hasBlackoutOverlap };
    },
);

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

const selectedDayHours = computed(() => {
    if (!selectedDate.value) return null;
    const dow = dayOfWeekFromYmd(selectedDate.value);
    if (dow === null) return null;
    const keyMap: Record<number, keyof OpeningHours> = {
        0: 'sun',
        1: 'mon',
        2: 'tue',
        3: 'wed',
        4: 'thu',
        5: 'fri',
        6: 'sat',
    };
    return openingHours.value[keyMap[dow]];
});

const startTimeMin = computed(() => selectedDayHours.value?.open ?? '00:00');

const startTimeMax = computed(() => {
    const close = selectedDayHours.value?.close;
    if (!close) return '23:59';
    return addMinutesToTime(close, -props.min_duration_minutes) ?? close;
});

const endTimeMin = computed(() => {
    if (startTime.value) {
        return addMinutesToTime(startTime.value, props.min_duration_minutes) ?? (selectedDayHours.value?.open ?? '00:00');
    }
    return selectedDayHours.value?.open ?? '00:00';
});

const endTimeMax = computed(() => {
    const close = selectedDayHours.value?.close ?? '23:59';
    if (startTime.value) {
        const maxFromStart = addMinutesToTime(startTime.value, props.max_duration_minutes);
        if (maxFromStart && maxFromStart < close) {
            return maxFromStart;
        }
    }
    return close;
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
    timeZone: APP_TIMEZONE,
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
    eventClassNames: (arg) => {
        if (arg.event.extendedProps?.type === 'pending') {
            return ['fc-event--pending'];
        }
        return [];
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

            loadedEvents.value = events;
            success(events);
        } catch (error) {
            loadError.value = true;
            failure(
                error instanceof Error
                    ? error
                    : new Error('No se pudo cargar la disponibilidad.'),
            );
        } finally {
            isLoading.value = false;
        }
    },
    datesSet: (info) => {
        if (isSyncingFromInput.value) {
            return;
        }

        const nextDate = info.startStr.slice(0, 10);
        if (nextDate !== selectedDate.value) {
            isSyncingFromCalendar.value = true;
            selectedDate.value = nextDate;
            void nextTick(() => {
                isSyncingFromCalendar.value = false;
            });
        }
    },
}));

const calendarRef = ref<InstanceType<typeof AppCalendar> | null>(null);
const endTimeRef = ref<HTMLInputElement | null>(null);

watch(startTime, (newStart) => {
    if (!newStart) return;

    const startMin = timeToMinutes(newStart);
    if (startMin === null) return;

    const currentEnd = timeToMinutes(endTime.value);
    const minEnd = startMin + props.min_duration_minutes;
    const maxEnd = startMin + props.max_duration_minutes;
    const wasEmpty = currentEnd === null;

    if (currentEnd === null || currentEnd < minEnd || currentEnd > maxEnd) {
        endTime.value = addMinutesToTime(newStart, props.min_duration_minutes) ?? '';
    }

    if (wasEmpty) {
        void nextTick(() => {
            endTimeRef.value?.focus();
        });
    }
});

watch(selectedDate, (date) => {
    if (isSyncingFromCalendar.value) {
        return;
    }

    const api = calendarRef.value?.getApi?.();
    if (api) {
        isSyncingFromInput.value = true;
        api.gotoDate(date);
        void nextTick(() => {
            isSyncingFromInput.value = false;
        });
    }

    const hours = selectedDayHours.value;
    if (!hours) return;

    const startMin = timeToMinutes(startTime.value);
    const openMin = timeToMinutes(hours.open);
    const closeMin = timeToMinutes(hours.close);

    if (startMin !== null && openMin !== null && closeMin !== null) {
        if (startMin < openMin || startMin >= closeMin) {
            startTime.value = '';
            endTime.value = '';
            return;
        }
    }

    const endMin = timeToMinutes(endTime.value);
    if (endMin !== null && closeMin !== null && endMin > closeMin) {
        endTime.value = '';
    }
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
                            class="h-2.5 w-2.5 rounded-sm bg-warning"
                        ></span>
                        Ocupado
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-sm border border-dashed border-blue-500 bg-blue-500 opacity-70"
                        ></span>
                        Solicitado
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-sm bg-muted-foreground"
                        ></span>
                        Bloqueado
                    </div>
                    <div class="ml-auto text-xs">
                        Zona horaria: {{ APP_TIMEZONE }}
                    </div>
                </div>

                <div
                    v-if="loadError"
                    class="mb-3 rounded-lg border border-border/60 p-4 text-sm text-destructive"
                >
                    No se pudo cargar la disponibilidad.
                </div>

                <AppCalendar
                    ref="calendarRef"
                    :options="calendarOptions"
                    :loading="isLoading"
                    no-today-highlight
                >
                    <template #loading>
                        <div class="flex h-full flex-col bg-card">
                            <!-- Toolbar -->
                            <div
                                class="flex items-center justify-between px-1 py-3"
                            >
                                <div class="flex gap-1">
                                    <Skeleton class="h-8 w-9 rounded-md" />
                                    <Skeleton class="h-8 w-9 rounded-md" />
                                    <Skeleton
                                        class="ml-1 h-8 w-12 rounded-md"
                                    />
                                </div>
                                <Skeleton class="h-7 w-52 rounded-md" />
                                <div class="w-24" />
                            </div>

                            <!-- Day header -->
                            <div class="border-t border-border/60 px-10 py-2">
                                <Skeleton class="mx-auto h-4 w-12 rounded" />
                            </div>

                            <!-- Time slots -->
                            <div
                                class="flex flex-1 flex-col border-t border-border/60"
                            >
                                <div
                                    v-for="i in 10"
                                    :key="i"
                                    class="flex flex-1 items-start border-b border-border/60"
                                >
                                    <Skeleton
                                        class="m-2 h-3 w-6 shrink-0 rounded"
                                    />
                                    <div class="flex-1" />
                                </div>
                            </div>
                        </div>
                    </template>
                </AppCalendar>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Crear solicitud</div>

                <Form
                    v-bind="reservationsRoutes.store.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-3"
                >
                    <div class="grid gap-1">
                        <label class="text-sm" for="date">Fecha</label>
                        <input
                            id="date"
                            v-model="selectedDate"
                            type="date"
                            :min="dateMin"
                            :max="dateMax"
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
                                step="300"
                                :min="startTimeMin"
                                :max="startTimeMax"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="end_time"> Fin </label>
                            <input
                                id="end_time"
                                ref="endTimeRef"
                                v-model="endTime"
                                type="time"
                                step="300"
                                :min="endTimeMin"
                                :max="endTimeMax"
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
                            Mín:
                            {{
                                minutesToDurationLabel(
                                    props.min_duration_minutes,
                                )
                            }}
                            · Máx:
                            {{
                                minutesToDurationLabel(
                                    props.max_duration_minutes,
                                )
                            }}
                            · Anticipación: {{ props.lead_time_min_hours }}h –
                            {{ props.lead_time_max_days }} días
                        </div>
                    </div>

                    <div
                        v-if="overlap"
                        class="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-700 dark:text-amber-400"
                    >
                        <div class="flex items-start gap-2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="mt-0.5 h-4 w-4 shrink-0"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <div>
                                <p v-if="overlap.hasApprovedOverlap">
                                    El horario se superpone con una reserva
                                    confirmada.
                                </p>
                                <p v-if="overlap.hasPendingOverlap">
                                    El horario se superpone con una solicitud
                                    pendiente.
                                </p>
                                <p v-if="overlap.hasBlackoutOverlap">
                                    El horario se superpone con un periodo
                                    bloqueado.
                                </p>
                            </div>
                        </div>
                    </div>

                    <InputError :message="errors.starts_at" />
                    <InputError :message="errors.ends_at" />
                    <InputError :message="errors.reservation" />

                    <Button
                        type="submit"
                        class="mt-2"
                        :disabled="processing || !canSubmit"
                    >
                        <Spinner v-if="processing" />
                        Enviar solicitud
                    </Button>

                    <Link
                        :href="reservationsRoutes.index().url"
                        class="text-sm text-muted-foreground underline underline-offset-4"
                    >
                        Volver
                    </Link>
                </Form>
            </div>
        </div>
    </div>
</template>
