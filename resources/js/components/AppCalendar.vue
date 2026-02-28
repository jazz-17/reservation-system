<script setup lang="ts">
import type { CalendarOptions } from '@fullcalendar/core';
import FullCalendar from '@fullcalendar/vue3';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    options: CalendarOptions;
    clickable?: boolean;
    loading?: boolean;
    noTodayHighlight?: boolean;
}>();

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);

defineExpose({
    getApi: () => calendarRef.value?.getApi(),
});
</script>

<template>
    <Card
        class="app-calendar relative gap-0 py-0"
        :class="{
            'app-calendar--clickable': clickable,
            'app-calendar--no-today': noTodayHighlight,
        }"
    >
        <CardContent class="relative p-2">
            <div v-if="loading" class="absolute inset-2 z-10 bg-card">
                <slot name="loading">
                    <Skeleton class="h-full w-full rounded-md" />
                </slot>
            </div>

            <div :class="{ invisible: loading }">
                <FullCalendar ref="calendarRef" :options="options" />
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
.app-calendar :deep(.fc) {
    --fc-page-bg-color: var(--card);
    --fc-border-color: color-mix(in oklab, var(--border) 60%, transparent);
    --fc-neutral-bg-color: color-mix(in oklab, var(--muted) 60%, transparent);
    --fc-today-bg-color: color-mix(in oklab, var(--primary) 10%, transparent);
    --fc-event-border-color: transparent;
    color: var(--foreground);
}

.app-calendar :deep(.fc .fc-button) {
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

.app-calendar :deep(.fc .fc-button:hover) {
    background: var(--accent);
    color: var(--accent-foreground);
}

.app-calendar :deep(.fc .fc-button:active),
.app-calendar :deep(.fc .fc-button.fc-button-active) {
    background: var(--accent);
    color: var(--accent-foreground);
}

.app-calendar :deep(.fc .fc-button:disabled) {
    opacity: 0.5;
    pointer-events: none;
}

.app-calendar :deep(.fc .fc-button:focus) {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--ring) 50%, transparent);
}

.app-calendar--clickable :deep(.fc .fc-daygrid-day) {
    cursor: pointer;
    transition: background-color 0.15s;
}

.app-calendar--clickable :deep(.fc .fc-daygrid-day:hover) {
    background: var(--accent);
}

.app-calendar--no-today :deep(.fc .fc-day-today) {
    background: transparent !important;
}
</style>
