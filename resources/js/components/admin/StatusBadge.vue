<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatReservationStatus } from '@/lib/formatters';
import type { ReservationStatus } from '@/types/admin';

const props = defineProps<{
    status: ReservationStatus;
}>();

const variantClasses = computed(() => {
    switch (props.status) {
        case 'pending':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
        case 'approved':
            return 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'rejected':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
        case 'cancelled':
            return 'border-transparent bg-muted text-muted-foreground';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
});
</script>

<template>
    <Badge variant="outline" :class="variantClasses">
        {{ formatReservationStatus(props.status) }}
    </Badge>
</template>
