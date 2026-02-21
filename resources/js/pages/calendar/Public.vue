<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { fetchJson } from '@/lib/http';
import { availability } from '@/routes/api/public';

type Interval = {
    start: string;
    end: string;
    reason?: string | null;
};

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
    busy: Interval[];
    blackouts: Interval[];
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

const toDateInput = (date: Date): string => date.toISOString().slice(0, 10);
const addDays = (date: Date, days: number): Date => {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);
    return copy;
};

const selectedDate = ref<string>(toDateInput(new Date()));

const from = computed(() => selectedDate.value);
const to = computed(() =>
    toDateInput(addDays(new Date(selectedDate.value), 6)),
);

const query = useQuery({
    queryKey: ['public-availability', from, to],
    queryFn: () =>
        fetchJson<AvailabilityResponse>(
            availability.url({ query: { from: from.value, to: to.value } }),
        ),
});

const formatTime = (iso: string, timezone: string): string => {
    const date = new Date(iso);
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const formatDate = (date: string): string => {
    const d = new Date(`${date}T00:00:00`);
    return new Intl.DateTimeFormat('es-PE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    }).format(d);
};
</script>

<template>
    <Head title="Calendario" />

    <PublicLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:gap-4">
                <div class="flex-1">
                    <h1 class="text-lg font-semibold">
                        Calendario de disponibilidad
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Vista pública (solo ocupado / libre).
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-sm text-muted-foreground" for="date">
                        Semana desde
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

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="day in query.data?.days ?? []"
                    :key="day.date"
                    class="rounded-lg border border-border/60 p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-sm font-medium">
                            {{ formatDate(day.date) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ day.open }}–{{ day.close }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <template v-if="day.slots?.length">
                            <div
                                v-for="slot in day.slots"
                                :key="slot.start"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                            >
                                <div>
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
                                </div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400':
                                            slot.status === 'free',
                                        'bg-amber-500/10 text-amber-700 dark:text-amber-400':
                                            slot.status === 'occupied',
                                        'bg-slate-500/10 text-slate-700 dark:text-slate-300':
                                            slot.status === 'blocked',
                                    }"
                                >
                                    {{
                                        slot.status === 'free'
                                            ? 'Libre'
                                            : slot.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </div>
                        </template>

                        <template v-else-if="day.blocks?.length">
                            <div
                                v-for="block in day.blocks"
                                :key="block.start"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                            >
                                <div>
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
                                </div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400':
                                            block.status === 'free',
                                        'bg-amber-500/10 text-amber-700 dark:text-amber-400':
                                            block.status === 'occupied',
                                        'bg-slate-500/10 text-slate-700 dark:text-slate-300':
                                            block.status === 'blocked',
                                    }"
                                >
                                    {{
                                        block.status === 'free'
                                            ? 'Libre'
                                            : block.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </div>
                        </template>

                        <template v-else-if="day.start_times?.length">
                            <div
                                v-for="start in day.start_times"
                                :key="start.start"
                                class="flex items-center justify-between rounded-md border border-border/60 px-3 py-2 text-sm"
                            >
                                <div>
                                    {{
                                        formatTime(
                                            start.start,
                                            query.data!.timezone,
                                        )
                                    }}
                                </div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400':
                                            start.status === 'free',
                                        'bg-amber-500/10 text-amber-700 dark:text-amber-400':
                                            start.status === 'occupied',
                                        'bg-slate-500/10 text-slate-700 dark:text-slate-300':
                                            start.status === 'blocked',
                                    }"
                                >
                                    {{
                                        start.status === 'free'
                                            ? 'Disponible'
                                            : start.status === 'occupied'
                                              ? 'Ocupado'
                                              : 'Bloqueado'
                                    }}
                                </span>
                            </div>
                        </template>

                        <div v-else class="text-sm text-muted-foreground">
                            Sin horarios configurados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
