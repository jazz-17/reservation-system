<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

defineOptions({ layout: [AppLayout, SettingsLayout] });

useBreadcrumbs([
    {
        title: 'Appearance settings',
        href: edit().url,
    },
]);
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance Settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update your account's appearance settings"
        />
        <AppearanceTabs />
    </div>
</template>
