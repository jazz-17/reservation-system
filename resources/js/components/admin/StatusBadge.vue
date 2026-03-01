<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatReservationStatus } from '@/lib/formatters';
import type { ReservationStatus } from '@/types/admin';

const props = defineProps<{
    status: ReservationStatus;
}>();

const badgeVariant = computed(() => {
    switch (props.status) {
        case 'pending':
            return 'warning';
        case 'approved':
            return 'success';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'secondary';
        default:
            return 'secondary';
    }
});
</script>

<template>
    <Badge :variant="badgeVariant">
        {{ formatReservationStatus(props.status) }}
    </Badge>
</template>
