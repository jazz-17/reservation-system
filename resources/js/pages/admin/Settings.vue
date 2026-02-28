<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { index as adminRequestsIndex } from '@/routes/admin/requests';
import {
    update as updateSettings,
    edit as editSettings,
} from '@/routes/admin/settings';
import type { AdminSettings, DayKey } from '@/types/admin';

const props = defineProps<{
    settings: AdminSettings;
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsIndex().url },
    { title: 'Configuración', href: editSettings().url },
]);

const days: DayKey[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

const dayLabels: Record<DayKey, string> = {
    mon: 'Lun',
    tue: 'Mar',
    wed: 'Mié',
    thu: 'Jue',
    fri: 'Vie',
    sat: 'Sáb',
    sun: 'Dom',
};

const formState = reactive<AdminSettings>({
    ...props.settings,
    notify_admin_emails: {
        to: props.settings.notify_admin_emails?.to ?? [],
        cc: props.settings.notify_admin_emails?.cc ?? [],
        bcc: props.settings.notify_admin_emails?.bcc ?? [],
    },
});

const emailsEnabled = computed(() => formState.email_notifications_enabled);

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

const timeToMinutes = (value: string): number | null => {
    const match = value.match(/^(\d{2}):(\d{2})$/);
    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    return hours * 60 + minutes;
};

type SchedulePreviewRow = {
    day: DayKey;
    open: string;
    close: string;
    summary: string;
};

const schedulePreview = computed<SchedulePreviewRow[]>(() => {
    return days.map((day) => {
        const open = formState.opening_hours[day]?.open ?? '00:00';
        const close = formState.opening_hours[day]?.close ?? '00:00';

        const openMin = timeToMinutes(open);
        const closeMin = timeToMinutes(close);

        if (openMin === null || closeMin === null || closeMin <= openMin) {
            return { day, open, close, summary: 'Cerrado' };
        }

        return {
            day,
            open,
            close,
            summary: 'Abierto',
        };
    });
});

const leadTimePreview = computed(() => {
    const minHours = Math.max(0, Number(formState.lead_time_min_hours) || 0);
    const maxDays = Math.max(0, Number(formState.lead_time_max_days) || 0);

    const now = new Date();
    const earliest = new Date(now.getTime() + minHours * 60 * 60 * 1000);
    const latest = new Date(now.getTime() + maxDays * 24 * 60 * 60 * 1000);

    const dateTimeFormatter = new Intl.DateTimeFormat('es-PE', {
        timeZone: formState.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });

    const dateFormatter = new Intl.DateTimeFormat('es-PE', {
        timeZone: formState.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

    return {
        earliest: dateTimeFormatter.format(earliest),
        latest: dateFormatter.format(latest),
    };
});
</script>

<template>
    <Head title="Configuración" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Configuración"
            subtitle="Ajusta reglas de reservas, horarios y notificaciones."
        />

        <Form
            v-bind="updateSettings.form()"
            :transform="() => formState"
            v-slot="{ errors, processing }"
            class="grid gap-6"
        >
            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">General</div>
                <div class="grid gap-4 md:grid-cols-1">
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
                </div>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Reglas</div>
                <div class="grid gap-4 md:grid-cols-3">
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
                        <InputError :message="errors.min_duration_minutes" />
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
                        <InputError :message="errors.max_duration_minutes" />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-sm" for="lead_time_min_hours"
                            >Anticipación mínima (h)</label
                        >
                        <input
                            id="lead_time_min_hours"
                            v-model.number="formState.lead_time_min_hours"
                            type="number"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                        <InputError :message="errors.lead_time_min_hours" />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-sm" for="lead_time_max_days"
                            >Anticipación máxima (días)</label
                        >
                        <input
                            id="lead_time_max_days"
                            v-model.number="formState.lead_time_max_days"
                            type="number"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                        <InputError :message="errors.lead_time_max_days" />
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
                            :message="errors.max_active_reservations_per_user"
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
                            v-model.number="formState.pending_expiration_hours"
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
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Horario de atención</div>
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

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">PDF</div>
                <div class="grid gap-1 md:max-w-md">
                    <label class="text-sm" for="pdf_template"
                        >Plantilla activa</label
                    >
                    <input
                        id="pdf_template"
                        v-model="formState.pdf_template"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="default"
                    />
                    <InputError :message="errors.pdf_template" />
                </div>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Notificaciones</div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2">
                            <input
                                id="email_notifications_enabled"
                                v-model="formState.email_notifications_enabled"
                                type="checkbox"
                                class="h-4 w-4"
                            />
                            <label
                                class="text-sm"
                                for="email_notifications_enabled"
                            >
                                Habilitar envío de correos
                            </label>
                            <InputError
                                :message="errors.email_notifications_enabled"
                            />
                        </div>
                        <p
                            v-if="!emailsEnabled"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            El envío de correos está deshabilitado. Los eventos
                            se registran en el sistema, pero no se enviarán
                            emails.
                        </p>
                    </div>
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
                            :disabled="!emailsEnabled"
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
                            :disabled="!emailsEnabled"
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
                            :disabled="!emailsEnabled"
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
                            :disabled="!emailsEnabled"
                        />
                        <label class="text-sm" for="notify_student_on_approval">
                            Notificar al estudiante (cambios de estado)
                        </label>
                        <InputError
                            :message="errors.notify_student_on_approval"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border/60 p-4">
                <div class="mb-3 text-sm font-medium">Previsualización</div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-md border border-border/60 p-3">
                        <div class="text-xs text-muted-foreground">
                            Duración
                        </div>
                        <div class="text-sm font-medium">
                            {{ formState.min_duration_minutes }}–{{
                                formState.max_duration_minutes
                            }}
                            min
                        </div>
                        <div class="mt-2 text-xs text-muted-foreground">
                            Ventana de reserva (según zona horaria)
                        </div>
                        <div class="text-sm">
                            Desde {{ leadTimePreview.earliest }}<br />
                            Hasta {{ leadTimePreview.latest }}
                        </div>
                        <div class="mt-2 text-xs text-muted-foreground">
                            Plantilla PDF
                        </div>
                        <div class="text-sm">
                            {{ formState.pdf_template || '—' }}
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Día</TableHead>
                                <TableHead>Horario</TableHead>
                                <TableHead>Resumen</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in schedulePreview"
                                :key="row.day"
                            >
                                <TableCell>
                                    {{ dayLabels[row.day] }}
                                </TableCell>
                                <TableCell>
                                    {{ row.open }}–{{ row.close }}
                                </TableCell>
                                <TableCell>
                                    {{ row.summary }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Button type="submit" :disabled="processing"> Guardar </Button>
            </div>
        </Form>
    </div>
</template>
