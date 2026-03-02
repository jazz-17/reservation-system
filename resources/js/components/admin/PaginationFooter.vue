<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    currentPage: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
    isLoading?: boolean;
}>();

defineEmits<{
    prev: [];
    next: [];
}>();
</script>

<template>
    <div class="flex items-center justify-between pt-2">
        <Button
            variant="outline"
            size="sm"
            :disabled="!hasPrevPage"
            @click="$emit('prev')"
        >
            Anterior
        </Button>
        <span class="text-sm text-muted-foreground">
            Página {{ currentPage }}
        </span>
        <Button
            variant="outline"
            size="sm"
            :disabled="!hasNextPage || isLoading"
            @click="$emit('next')"
        >
            Siguiente
        </Button>
    </div>
</template>
