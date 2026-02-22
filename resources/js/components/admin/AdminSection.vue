<script setup lang="ts">
defineProps<{ title?: string }>();
</script>

<template>
    <div class="rounded-lg border border-border/60 p-4">
        <div v-if="title" class="text-sm font-medium">{{ title }}</div>
        <slot />
    </div>
</template>
