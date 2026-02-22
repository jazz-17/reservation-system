<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatArtifactKind, formatDateTime } from '@/lib/formatters';
import {
    index as artifactsIndex,
    retry as retryArtifact,
} from '@/routes/admin/artifacts';
import type { Artifact } from '@/types/admin';

const props = defineProps<{
    artifacts: Artifact[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Reintentos', href: artifactsIndex().url },
]);

const retry = (id: number): void => {
    router.post(retryArtifact(id).url);
};
</script>

<template>
    <Head title="Reintentos" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Reintentos"
            subtitle="Artefactos fallidos (PDF / correos) para reintentar."
        />

        <div
            v-if="props.artifacts.length === 0"
            class="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground"
        >
            No hay artefactos fallidos.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="a in props.artifacts"
                :key="a.id"
                class="rounded-lg border border-border/60 p-4"
            >
                <div
                    class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                >
                    <div class="space-y-1">
                        <div class="text-sm font-medium">
                            {{ formatArtifactKind(a.kind) }} · Reserva #{{
                                a.reservation.id
                            }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ a.reservation.user?.name ?? '—' }} ·
                            {{ a.reservation.user?.email ?? '—' }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ formatDateTime(a.reservation.starts_at) }} —
                            {{ formatDateTime(a.reservation.ends_at) }}
                        </div>
                        <div
                            v-if="a.last_error"
                            class="mt-2 rounded-md bg-muted/40 p-3 text-xs"
                        >
                            {{ a.last_error }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Intentos: {{ a.attempts }} · Último intento:
                            {{
                                a.last_attempt_at
                                    ? formatDateTime(a.last_attempt_at)
                                    : '—'
                            }}
                        </div>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        class="self-start md:self-auto"
                        @click="retry(a.id)"
                    >
                        Reintentar
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
