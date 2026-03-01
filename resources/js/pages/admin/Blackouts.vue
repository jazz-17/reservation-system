<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatDateTime } from '@/lib/formatters';
import adminBlackoutsRoutes from '@/routes/admin/blackouts';
import adminRequestsRoutes from '@/routes/admin/requests';
import type { Blackout } from '@/types/admin';

const props = defineProps<{
    blackouts: Blackout[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Blackouts', href: adminBlackoutsRoutes.index().url },
]);

const remove = (id: number): void => {
    router.delete(adminBlackoutsRoutes.destroy(id).url);
};
</script>

<template>
    <Head title="Blackouts" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Blackouts"
            subtitle="Fechas/horas no reservables (mantenimiento, feriados, etc.)."
        />

        <AdminSection title="Crear blackout">
            <Form
                v-bind="adminBlackoutsRoutes.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <div class="grid gap-1">
                    <Label for="starts_at">Inicio</Label>
                    <Input id="starts_at" name="starts_at" type="datetime-local" />
                    <InputError :message="errors.starts_at" />
                </div>

                <div class="grid gap-1">
                    <Label for="ends_at">Fin</Label>
                    <Input id="ends_at" name="ends_at" type="datetime-local" />
                    <InputError :message="errors.ends_at" />
                </div>

                <div class="grid gap-1 md:col-span-2">
                    <Label for="reason">Motivo</Label>
                    <Input id="reason" name="reason" type="text" placeholder="Mantenimiento" />
                    <InputError :message="errors.reason" />
                </div>

                <div class="flex items-center justify-end md:col-span-2">
                    <Button type="submit" :disabled="processing">
                        Guardar
                    </Button>
                </div>
            </Form>
        </AdminSection>

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
                    <ConfirmDialog
                        title="¿Eliminar este bloqueo?"
                        description="Esta acción no se puede deshacer."
                        confirm-label="Eliminar"
                        variant="destructive"
                        @confirm="remove(b.id)"
                    >
                        <template #trigger>
                            <Button type="button" size="sm">
                                Eliminar
                            </Button>
                        </template>
                    </ConfirmDialog>
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
</template>
