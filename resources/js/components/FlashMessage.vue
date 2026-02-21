<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';

type FlashProps = {
    flash?: {
        success?: string | null;
    };
};

const page = usePage<FlashProps>();
</script>

<template>
    <div v-if="page.props.flash?.success" class="px-4 pt-4">
        <Alert>
            <AlertDescription>
                {{ page.props.flash.success }}
            </AlertDescription>
        </Alert>
    </div>
</template>
