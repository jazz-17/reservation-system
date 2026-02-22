<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-lg font-semibold">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-muted-foreground">
                {{ subtitle }}
            </p>
        </div>
        <slot />
    </div>
</template>
