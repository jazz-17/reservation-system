<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index as artifactsIndex,
    retry as retryArtifact,
} from '@/routes/admin/artifacts';
import { type BreadcrumbItem } from '@/types';

type Artifact = {
    id: number;
    kind: 'pdf' | 'email_admin' | 'email_student';
    status: 'pending' | 'sent' | 'failed';
    attempts: number;
    last_error?: string | null;
    last_attempt_at?: string | null;
    reservation: {
        id: number;
        starts_at: string;
        ends_at: string;
        user?: { name: string; email: string } | null;
    };
};

const props = defineProps<{
    artifacts: Artifact[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Reintentos', href: artifactsIndex().url },
];

const kindLabel = (kind: Artifact['kind']): string => {
    switch (kind) {
        case 'pdf':
            return 'PDF';
        case 'email_admin':
            return 'Email (Admin)';
        case 'email_student':
            return 'Email (Estudiante)';
    }
};

const formatDateTime = (iso: string): string => {
    const d = new Date(iso);
    return new Intl.DateTimeFormat('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
};

const retry = (id: number): void => {
    router.post(retryArtifact(id).url);
};
</script>

<template>
    <Head title="Reintentos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Reintentos</h1>
                <p class="text-sm text-muted-foreground">
                    Artefactos fallidos (PDF / correos) para reintentar.
                </p>
            </div>

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
                                {{ kindLabel(a.kind) }} · Reserva #{{
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

                        <button
                            type="button"
                            class="self-start rounded-md bg-primary px-3 py-2 text-xs text-primary-foreground md:self-auto"
                            @click="retry(a.id)"
                        >
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
