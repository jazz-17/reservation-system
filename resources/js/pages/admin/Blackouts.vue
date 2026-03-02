<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { APP_TIMEZONE, formatDateTime } from '@/lib/formatters';
import adminBlackoutsRoutes from '@/routes/admin/blackouts';
import adminRequestsRoutes from '@/routes/admin/requests';
import type { Blackout, RecurringBlackout } from '@/types/admin';

const props = defineProps<{
    blackouts: Blackout[];
    recurring_blackouts: RecurringBlackout[];
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Blackouts', href: adminBlackoutsRoutes.index().url },
]);

const remove = (id: number): void => {
    router.delete(adminBlackoutsRoutes.destroy(id).url);
};

const weekdayOptions: Array<{ value: number; label: string }> = [
    { value: 0, label: 'Dom' },
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mié' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sáb' },
];

const weekdayLabel = (weekday: number): string =>
    weekdayOptions.find((d) => d.value === weekday)?.label ?? String(weekday);

const rangeLabel = (b: RecurringBlackout): string => {
    const startsOn = b.starts_on ?? null;
    const endsOn = b.ends_on ?? null;

    if (!startsOn && !endsOn) {
        return 'Siempre';
    }

    if (startsOn && !endsOn) {
        return `Desde ${startsOn}`;
    }

    if (!startsOn && endsOn) {
        return `Hasta ${endsOn}`;
    }

    return `${startsOn} → ${endsOn}`;
};

const removeRecurring = (id: number): void => {
    router.delete(adminBlackoutsRoutes.recurring.destroy(id).url);
};
</script>

<template>
    <Head title="Blackouts" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Blackouts"
            subtitle="Fechas/horas no reservables (mantenimiento, feriados, etc.)."
        />

        <AdminSection title="Crear blackout recurrente">
            <Form
                v-bind="adminBlackoutsRoutes.recurring.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <div class="grid gap-1">
                    <Label for="weekday">Día</Label>
                    <NativeSelect id="weekday" name="weekday" :default-value="1">
                        <option
                            v-for="d in weekdayOptions"
                            :key="d.value"
                            :value="d.value"
                        >
                            {{ d.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.weekday" />
                </div>

                <div class="grid gap-1">
                    <Label for="starts_time">Inicio</Label>
                    <Input id="starts_time" name="starts_time" type="time" />
                    <InputError :message="errors.starts_time" />
                </div>

                <div class="grid gap-1">
                    <Label for="ends_time">Fin</Label>
                    <Input id="ends_time" name="ends_time" type="time" />
                    <InputError :message="errors.ends_time" />
                </div>

                <div class="grid gap-1">
                    <Label for="starts_on">Desde (opcional)</Label>
                    <Input id="starts_on" name="starts_on" type="date" />
                    <InputError :message="errors.starts_on" />
                </div>

                <div class="grid gap-1">
                    <Label for="ends_on">Hasta (opcional)</Label>
                    <Input id="ends_on" name="ends_on" type="date" />
                    <InputError :message="errors.ends_on" />
                </div>

                <div class="grid gap-1 md:col-span-2">
                    <Label for="reason_recurring">Motivo</Label>
                    <Input
                        id="reason_recurring"
                        name="reason"
                        type="text"
                        placeholder="Mantenimiento"
                    />
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
            <div class="border-b border-border/60 px-4 py-3 text-sm font-medium">
                Lista (recurrentes)
            </div>
            <div class="divide-y divide-border/60">
                <div
                    v-for="b in props.recurring_blackouts"
                    :key="b.id"
                    class="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm">
                        <div class="font-medium">
                            {{ weekdayLabel(b.weekday) }}
                            {{ b.starts_time.slice(0, 5) }} —
                            {{ b.ends_time.slice(0, 5) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ rangeLabel(b) }} · {{ b.reason ?? '—' }}
                        </div>
                    </div>
                    <ConfirmDialog
                        title="¿Eliminar este bloqueo recurrente?"
                        description="Esta acción no se puede deshacer."
                        confirm-label="Eliminar"
                        variant="destructive"
                        @confirm="removeRecurring(b.id)"
                    >
                        <template #trigger>
                            <Button type="button" size="sm">
                                Eliminar
                            </Button>
                        </template>
                    </ConfirmDialog>
                </div>
                <div
                    v-if="props.recurring_blackouts.length === 0"
                    class="px-4 py-6 text-sm text-muted-foreground"
                >
                    No hay blackouts recurrentes.
                </div>
            </div>
        </div>

        <AdminSection title="Crear blackout">
            <Form
                v-bind="adminBlackoutsRoutes.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <div class="grid gap-1">
                    <Label for="starts_at">Inicio</Label>
                    <Input id="starts_at" name="starts_at" type="datetime-local" />
                    <div class="text-xs text-muted-foreground">
                        Hora en {{ APP_TIMEZONE }}.
                    </div>
                    <InputError :message="errors.starts_at" />
                </div>

                <div class="grid gap-1">
                    <Label for="ends_at">Fin</Label>
                    <Input id="ends_at" name="ends_at" type="datetime-local" />
                    <div class="text-xs text-muted-foreground">
                        Hora en {{ APP_TIMEZONE }}.
                    </div>
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
