<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    destroy,
    index as blackoutsIndex,
    store as storeBlackout,
} from '@/routes/admin/blackouts';
import { type BreadcrumbItem } from '@/types';

type Blackout = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason?: string | null;
};

const props = defineProps<{
    blackouts: Blackout[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Blackouts', href: blackoutsIndex().url },
];

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

const remove = (id: number): void => {
    if (!confirm('¿Eliminar este bloqueo?')) {
        return;
    }

    router.delete(destroy(id).url);
};
</script>

<template>
    <Head title="Blackouts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Blackouts</h1>
                <p class="text-sm text-muted-foreground">
                    Fechas/horas no reservables (mantenimiento, feriados, etc.).
                </p>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="text-sm font-medium">Crear blackout</div>

                <Form
                    v-bind="storeBlackout.form()"
                    v-slot="{ errors, processing }"
                    class="mt-4 grid gap-3 md:grid-cols-2"
                >
                    <div class="grid gap-1">
                        <label class="text-sm" for="starts_at">Inicio</label>
                        <input
                            id="starts_at"
                            name="starts_at"
                            type="datetime-local"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                        <InputError :message="errors.starts_at" />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-sm" for="ends_at">Fin</label>
                        <input
                            id="ends_at"
                            name="ends_at"
                            type="datetime-local"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                        <InputError :message="errors.ends_at" />
                    </div>

                    <div class="grid gap-1 md:col-span-2">
                        <label class="text-sm" for="reason">Motivo</label>
                        <input
                            id="reason"
                            name="reason"
                            type="text"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            placeholder="Mantenimiento"
                        />
                        <InputError :message="errors.reason" />
                    </div>

                    <div class="flex items-center justify-end md:col-span-2">
                        <button
                            type="submit"
                            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            :disabled="processing"
                        >
                            Guardar
                        </button>
                    </div>
                </Form>
            </div>

            <div class="rounded-lg border border-border/60">
                <div
                    class="border-b border-border/60 px-4 py-3 text-sm font-medium"
                >
                    Lista
                </div>
                <div class="divide-y divide-border/60">
                    <div
                        v-for="b in props.blackouts"
                        :key="b.id"
                        class="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="text-sm">
                            <div class="font-medium">
                                {{ formatDateTime(b.starts_at) }} —
                                {{ formatDateTime(b.ends_at) }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ b.reason ?? '—' }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="self-start rounded-md border border-border/60 px-3 py-1.5 text-xs md:self-auto"
                            @click="remove(b.id)"
                        >
                            Eliminar
                        </button>
                    </div>
                    <div
                        v-if="props.blackouts.length === 0"
                        class="px-4 py-6 text-sm text-muted-foreground"
                    >
                        No hay blackouts.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
