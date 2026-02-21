<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/http';
import { availability } from '@/routes/api/public';
import {
    index as reservationsIndex,
    store as storeReservation,
} from '@/routes/reservations';
import { type BreadcrumbItem } from '@/types';

type Slot = {
    start: string;
    end: string;
    status: 'free' | 'occupied' | 'blocked';
};

type StartTime = {
    start: string;
    status: 'free' | 'occupied' | 'blocked';
};

type DayAvailability = {
    date: string;
    open: string;
    close: string;
    slots?: Slot[];
    blocks?: Slot[];
    start_times?: StartTime[];
};

type AvailabilityResponse = {
    timezone: string;
    booking_mode: 'fixed_duration' | 'variable_duration' | 'predefined_blocks';
    slot_duration_minutes: number;
    slot_step_minutes: number;
    min_duration_minutes: number;
    max_duration_minutes: number;
    days: DayAvailability[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis reservas', href: reservationsIndex().url },
    { title: 'Nueva solicitud', href: '/reservas/nueva' },
];

const toDateInput = (date: Date): string => date.toISOString().slice(0, 10);
const selectedDate = ref<string>(toDateInput(new Date()));

const selectedStart = ref<string | null>(null);
const selectedEnd = ref<string | null>(null);

const durationMinutes = ref<number | null>(null);

const query = useQuery({
    queryKey: ['public-availability-day', selectedDate],
    queryFn: () =>
        fetchJson<AvailabilityResponse>(
            availability.url({
                query: { from: selectedDate.value, to: selectedDate.value },
            }),
        ),
});

const day = computed(() => query.data?.days?.[0] ?? null);
const mode = computed(() => query.data?.booking_mode ?? 'fixed_duration');

const variableDurationOptions = computed(() => {
    const min = query.data?.min_duration_minutes ?? 60;
    const max = query.data?.max_duration_minutes ?? 120;
    const step = query.data?.slot_step_minutes ?? 30;
    const options: number[] = [];
    for (let d = min; d <= max; d += step) {
        options.push(d);
    }
    return options;
});

const selectSlot = (slot: Slot): void => {
    if (slot.status !== 'free') {
        return;
    }

    selectedStart.value = slot.start;
    selectedEnd.value = slot.end;
    durationMinutes.value = null;
};

const selectStartTime = (start: StartTime): void => {
    if (start.status !== 'free') {
        return;
    }

    selectedStart.value = start.start;
    selectedEnd.value = null;
};

const computeVariableEnd = (): void => {
    if (!selectedStart.value || !durationMinutes.value) {
        selectedEnd.value = null;
        return;
    }

    const start = new Date(selectedStart.value);
    const end = new Date(start.getTime() + durationMinutes.value * 60_000);
    selectedEnd.value = end.toISOString();
};

watch([selectedStart, durationMinutes], () => {
    if (mode.value === 'variable_duration') {
        computeVariableEnd();
    }
});

const formatTime = (iso: string, timezone: string): string => {
    const date = new Date(iso);
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};
</script>

<template>
    <Head title="Nueva solicitud" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:gap-4">
                <div class="flex-1">
                    <h1 class="text-lg font-semibold">Nueva solicitud</h1>
                    <p class="text-sm text-muted-foreground">
                        Las solicitudes se crean en estado
                        <span class="font-medium">Pendiente</span>.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-muted-foreground" for="date">
                        Fecha
                    </label>
                    <input
                        id="date"
                        v-model="selectedDate"
                        type="date"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    />
                </div>
            </div>

            <div
                v-if="query.isLoading"
                class="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground"
            >
                Cargando disponibilidad…
            </div>

            <div
                v-else-if="query.isError"
                class="rounded-lg border border-border/60 p-6 text-sm text-destructive"
            >
                No se pudo cargar la disponibilidad.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-sm font-medium">Horarios</div>
                        <div class="text-xs text-muted-foreground">
                            {{ day?.open }}–{{ day?.close }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <template v-if="day?.slots?.length">
                            <button
                                v-for="slot in day.slots"
                                :key="slot.start"
                                type="button"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                                :class="{
                                    'opacity-50': slot.status !== 'free',
                                    'border-primary':
                                        selectedStart === slot.start,
                                }"
                                @click="selectSlot(slot)"
                            >
                                <span>
                                    {{
                                        formatTime(
                                            slot.start,
                                            query.data!.timezone,
                                        )
                                    }}
                                    -
                                    {{
                                        formatTime(
                                            slot.end,
                                            query.data!.timezone,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        slot.status === 'free'
                                            ? 'Libre'
                                            : slot.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </button>
                        </template>

                        <template v-else-if="day?.blocks?.length">
                            <button
                                v-for="block in day.blocks"
                                :key="block.start"
                                type="button"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                                :class="{
                                    'opacity-50': block.status !== 'free',
                                    'border-primary':
                                        selectedStart === block.start,
                                }"
                                @click="selectSlot(block)"
                            >
                                <span>
                                    {{
                                        formatTime(
                                            block.start,
                                            query.data!.timezone,
                                        )
                                    }}
                                    -
                                    {{
                                        formatTime(
                                            block.end,
                                            query.data!.timezone,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        block.status === 'free'
                                            ? 'Libre'
                                            : block.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </button>
                        </template>

                        <template v-else-if="day?.start_times?.length">
                            <button
                                v-for="start in day.start_times"
                                :key="start.start"
                                type="button"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                                :class="{
                                    'opacity-50': start.status !== 'free',
                                    'border-primary':
                                        selectedStart === start.start,
                                }"
                                @click="selectStartTime(start)"
                            >
                                <span>
                                    {{
                                        formatTime(
                                            start.start,
                                            query.data!.timezone,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        start.status === 'free'
                                            ? 'Disponible'
                                            : start.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </button>

                            <div
                                v-if="
                                    selectedStart &&
                                    mode === 'variable_duration'
                                "
                                class="mt-2 flex items-center gap-2"
                            >
                                <label
                                    class="text-sm text-muted-foreground"
                                    for="duration"
                                >
                                    Duración
                                </label>
                                <select
                                    id="duration"
                                    v-model.number="durationMinutes"
                                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option :value="null" disabled>
                                        Selecciona…
                                    </option>
                                    <option
                                        v-for="d in variableDurationOptions"
                                        :key="d"
                                        :value="d"
                                    >
                                        {{ d }} min
                                    </option>
                                </select>
                            </div>
                        </template>

                        <div v-else class="text-sm text-muted-foreground">
                            Sin horarios configurados.
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 text-sm font-medium">
                        Confirmar solicitud
                    </div>

                    <Form
                        v-bind="storeReservation.form()"
                        v-slot="{ errors, processing }"
                        class="flex flex-col gap-3"
                    >
                        <input
                            type="hidden"
                            name="starts_at"
                            :value="selectedStart ?? ''"
                        />
                        <input
                            type="hidden"
                            name="ends_at"
                            :value="selectedEnd ?? ''"
                        />

                        <div class="text-sm">
                            <div class="text-muted-foreground">Inicio</div>
                            <div class="font-medium">
                                {{
                                    selectedStart
                                        ? formatTime(
                                              selectedStart,
                                              query.data!.timezone,
                                          )
                                        : '—'
                                }}
                            </div>
                        </div>

                        <div class="text-sm">
                            <div class="text-muted-foreground">Fin</div>
                            <div class="font-medium">
                                {{
                                    selectedEnd
                                        ? formatTime(
                                              selectedEnd,
                                              query.data!.timezone,
                                          )
                                        : mode === 'fixed_duration'
                                          ? 'Se calcula automáticamente'
                                          : '—'
                                }}
                            </div>
                        </div>

                        <InputError :message="errors.starts_at" />
                        <InputError :message="errors.ends_at" />
                        <InputError :message="errors.reservation" />

                        <button
                            type="submit"
                            class="mt-2 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            :disabled="
                                processing ||
                                !selectedStart ||
                                (mode !== 'fixed_duration' && !selectedEnd)
                            "
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
    </AppLayout>
</template>
