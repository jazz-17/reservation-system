<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    update as updateSettings,
    edit as editSettings,
} from '@/routes/admin/settings';
import { type BreadcrumbItem } from '@/types';

type OpeningHours = Record<
    'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun',
    { open: string; close: string }
>;

type PredefinedBlocks = Record<
    'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun',
    { start: string; end: string }[]
>;

type Settings = {
    timezone: string;
    opening_hours: OpeningHours;
    booking_mode: 'fixed_duration' | 'variable_duration' | 'predefined_blocks';
    slot_duration_minutes: number;
    slot_step_minutes: number;
    min_duration_minutes: number;
    max_duration_minutes: number;
    lead_time_min_hours: number;
    lead_time_max_days: number;
    max_active_reservations_per_user: number;
    weekly_quota_per_school_base: number;
    pending_expiration_hours: number;
    cancel_cutoff_hours: number;
    notify_admin_emails: { to: string[]; cc: string[]; bcc: string[] };
    notify_student_on_approval: boolean;
    pdf_template: string;
    predefined_blocks: PredefinedBlocks;
};

const props = defineProps<{
    settings: Settings;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/solicitudes' },
    { title: 'Configuración', href: editSettings().url },
];

const days: Array<keyof OpeningHours> = [
    'mon',
    'tue',
    'wed',
    'thu',
    'fri',
    'sat',
    'sun',
];

const dayLabels: Record<keyof OpeningHours, string> = {
    mon: 'Lun',
    tue: 'Mar',
    wed: 'Mié',
    thu: 'Jue',
    fri: 'Vie',
    sat: 'Sáb',
    sun: 'Dom',
};

const formState = reactive<Settings>({
    ...props.settings,
    notify_admin_emails: {
        to: props.settings.notify_admin_emails?.to ?? [],
        cc: props.settings.notify_admin_emails?.cc ?? [],
        bcc: props.settings.notify_admin_emails?.bcc ?? [],
    },
});

const toTextarea = (values: string[]): string => values.join('\n');
const fromTextarea = (value: string): string[] =>
    value
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);

const notifyTo = computed({
    get: () => toTextarea(formState.notify_admin_emails.to),
    set: (v: string) => {
        formState.notify_admin_emails.to = fromTextarea(v);
    },
});

const notifyCc = computed({
    get: () => toTextarea(formState.notify_admin_emails.cc),
    set: (v: string) => {
        formState.notify_admin_emails.cc = fromTextarea(v);
    },
});

const notifyBcc = computed({
    get: () => toTextarea(formState.notify_admin_emails.bcc),
    set: (v: string) => {
        formState.notify_admin_emails.bcc = fromTextarea(v);
    },
});

const addBlock = (day: keyof PredefinedBlocks): void => {
    formState.predefined_blocks[day].push({ start: '08:00', end: '09:00' });
};

const removeBlock = (day: keyof PredefinedBlocks, index: number): void => {
    formState.predefined_blocks[day].splice(index, 1);
};
</script>

<template>
    <Head title="Configuración" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Configuración</h1>
                <p class="text-sm text-muted-foreground">
                    Ajusta reglas de reservas, horarios y notificaciones.
                </p>
            </div>

            <Form
                v-bind="updateSettings.form()"
                :transform="() => formState"
                v-slot="{ errors, processing }"
                class="grid gap-6"
            >
                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 text-sm font-medium">General</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1">
                            <label class="text-sm" for="timezone"
                                >Zona horaria</label
                            >
                            <input
                                id="timezone"
                                v-model="formState.timezone"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                placeholder="America/Lima"
                            />
                            <InputError :message="errors.timezone" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="booking_mode"
                                >Modo de reserva</label
                            >
                            <select
                                id="booking_mode"
                                v-model="formState.booking_mode"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="fixed_duration">
                                    Duración fija
                                </option>
                                <option value="variable_duration">
                                    Duración variable
                                </option>
                                <option value="predefined_blocks">
                                    Bloques predefinidos
                                </option>
                            </select>
                            <InputError :message="errors.booking_mode" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 text-sm font-medium">Reglas</div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-1">
                            <label class="text-sm" for="slot_duration_minutes"
                                >Duración (min)</label
                            >
                            <input
                                id="slot_duration_minutes"
                                v-model.number="formState.slot_duration_minutes"
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="errors.slot_duration_minutes"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="slot_step_minutes"
                                >Paso (min)</label
                            >
                            <input
                                id="slot_step_minutes"
                                v-model.number="formState.slot_step_minutes"
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError :message="errors.slot_step_minutes" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="max_active"
                                >Máx. activas por usuario</label
                            >
                            <input
                                id="max_active"
                                v-model.number="
                                    formState.max_active_reservations_per_user
                                "
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="
                                    errors.max_active_reservations_per_user
                                "
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="weekly_quota"
                                >Cuota semanal Escuela/Base</label
                            >
                            <input
                                id="weekly_quota"
                                v-model.number="
                                    formState.weekly_quota_per_school_base
                                "
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="errors.weekly_quota_per_school_base"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="pending_expiration"
                                >Expiración pendiente (h)</label
                            >
                            <input
                                id="pending_expiration"
                                v-model.number="
                                    formState.pending_expiration_hours
                                "
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="errors.pending_expiration_hours"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm" for="cancel_cutoff_hours"
                                >Corte cancelación (h)</label
                            >
                            <input
                                id="cancel_cutoff_hours"
                                v-model.number="formState.cancel_cutoff_hours"
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError :message="errors.cancel_cutoff_hours" />
                        </div>
                    </div>

                    <div
                        class="mt-4 grid gap-4 md:grid-cols-2"
                        v-if="formState.booking_mode === 'variable_duration'"
                    >
                        <div class="grid gap-1">
                            <label class="text-sm" for="min_duration_minutes"
                                >Duración mínima (min)</label
                            >
                            <input
                                id="min_duration_minutes"
                                v-model.number="formState.min_duration_minutes"
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="errors.min_duration_minutes"
                            />
                        </div>
                        <div class="grid gap-1">
                            <label class="text-sm" for="max_duration_minutes"
                                >Duración máxima (min)</label
                            >
                            <input
                                id="max_duration_minutes"
                                v-model.number="formState.max_duration_minutes"
                                type="number"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <InputError
                                :message="errors.max_duration_minutes"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 text-sm font-medium">
                        Horario de atención
                    </div>
                    <div class="grid gap-2">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="grid items-center gap-2 md:grid-cols-[80px_1fr_1fr]"
                        >
                            <div class="text-sm text-muted-foreground">
                                {{ dayLabels[day] }}
                            </div>
                            <input
                                v-model="formState.opening_hours[day].open"
                                type="time"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <input
                                v-model="formState.opening_hours[day].close"
                                type="time"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                    </div>
                    <InputError :message="errors.opening_hours" />
                </div>

                <div
                    v-if="formState.booking_mode === 'predefined_blocks'"
                    class="rounded-lg border border-border/60 p-4"
                >
                    <div class="mb-3 text-sm font-medium">
                        Bloques predefinidos
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="rounded-md border border-border/60 p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <div class="text-sm font-medium">
                                    {{ dayLabels[day] }}
                                </div>
                                <button
                                    type="button"
                                    class="text-xs underline underline-offset-4"
                                    @click="addBlock(day)"
                                >
                                    Agregar
                                </button>
                            </div>
                            <div class="grid gap-2">
                                <div
                                    v-for="(block, i) in formState
                                        .predefined_blocks[day]"
                                    :key="i"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="block.start"
                                        type="time"
                                        class="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                    <input
                                        v-model="block.end"
                                        type="time"
                                        class="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                    <button
                                        type="button"
                                        class="text-xs text-muted-foreground underline underline-offset-4"
                                        @click="removeBlock(day, i)"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <InputError :message="errors.predefined_blocks" />
                </div>

                <div class="rounded-lg border border-border/60 p-4">
                    <div class="mb-3 text-sm font-medium">Notificaciones</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1">
                            <label class="text-sm" for="notify_to"
                                >Emails admin (TO)</label
                            >
                            <textarea
                                id="notify_to"
                                v-model="notifyTo"
                                rows="4"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="uno@ejemplo.com"
                            />
                            <InputError
                                :message="errors['notify_admin_emails.to']"
                            />
                        </div>
                        <div class="grid gap-1">
                            <label class="text-sm" for="notify_cc"
                                >Emails admin (CC)</label
                            >
                            <textarea
                                id="notify_cc"
                                v-model="notifyCc"
                                rows="4"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="errors['notify_admin_emails.cc']"
                            />
                        </div>
                        <div class="grid gap-1 md:col-span-2">
                            <label class="text-sm" for="notify_bcc"
                                >Emails admin (BCC)</label
                            >
                            <textarea
                                id="notify_bcc"
                                v-model="notifyBcc"
                                rows="3"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="errors['notify_admin_emails.bcc']"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                id="notify_student_on_approval"
                                v-model="formState.notify_student_on_approval"
                                type="checkbox"
                                class="h-4 w-4"
                            />
                            <label
                                class="text-sm"
                                for="notify_student_on_approval"
                            >
                                Notificar al estudiante
                            </label>
                            <InputError
                                :message="errors.notify_student_on_approval"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
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
    </AppLayout>
</template>
