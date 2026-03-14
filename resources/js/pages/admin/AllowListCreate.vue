<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSection from '@/components/admin/AdminSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import adminAllowListRoutes from '@/routes/admin/allow-list';
import adminRequestsRoutes from '@/routes/admin/requests';

const props = defineProps<{
    schools: Array<{
        id: number;
        name: string;
        code: string | null;
        base_year_min: number;
        base_year_max: number;
    }>;
}>();

useBreadcrumbs([
    { title: 'Admin', href: adminRequestsRoutes.index().url },
    { title: 'Allow-list', href: adminAllowListRoutes.index().url },
    { title: 'Agregar', href: adminAllowListRoutes.create().url },
]);

const selectedSchoolId = ref<string>('');
const studentCode = ref<string>('');

const selectedSchool = computed(() => {
    const id = Number(selectedSchoolId.value);
    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return props.schools.find((s) => s.id === id) ?? null;
});

const derivedBaseYear = computed<number | null>(() => {
    const normalized = studentCode.value.trim().replaceAll(' ', '');
    if (!/^\d{2,32}$/.test(normalized)) {
        return null;
    }

    const yy = Number(normalized.slice(0, 2));
    if (!Number.isFinite(yy)) {
        return null;
    }

    const year = 2000 + yy;
    return year >= 2000 && year <= 2100 ? year : null;
});

const derivedBaseLabel = computed(() => {
    if (derivedBaseYear.value === null) {
        return '—';
    }

    const yy = String(derivedBaseYear.value % 100).padStart(2, '0');
    return `B${yy} (${derivedBaseYear.value})`;
});
</script>

<template>
    <Head title="Agregar correo — Allow-list" />

    <div class="flex flex-col gap-4 p-4">
        <AdminPageHeader
            title="Agregar correo"
            subtitle="El alumno puede crear su cuenta con este correo. La escuela, base y código se copiarán al momento del registro y no actualizarán cuentas ya creadas."
        />

        <AdminSection title="Nuevo correo">
            <Form
                v-bind="adminAllowListRoutes.store.form()"
                v-slot="{ errors, processing }"
                class="mt-4 grid gap-3"
            >
                <div class="grid gap-1">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="tu@correo.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-1">
                    <Label for="professional_school_id">Escuela profesional</Label>
                    <NativeSelect
                        id="professional_school_id"
                        v-model="selectedSchoolId"
                        name="professional_school_id"
                        required
                    >
                        <option value="" disabled>Selecciona una escuela</option>
                        <option
                            v-for="s in props.schools"
                            :key="s.id"
                            :value="String(s.id)"
                        >
                            {{ s.name }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.professional_school_id" />
                </div>

                <div class="grid gap-1">
                    <Label for="student_code">Código</Label>
                    <Input
                        id="student_code"
                        v-model="studentCode"
                        name="student_code"
                        type="text"
                        required
                        inputmode="numeric"
                        placeholder="20200111"
                    />
                    <InputError :message="errors.student_code" />
                    <div class="text-xs text-muted-foreground">
                        Base derivada: {{ derivedBaseLabel }}
                        <span v-if="selectedSchool" class="ml-1">
                            (Rango permitido: {{ selectedSchool.base_year_min }}–{{ selectedSchool.base_year_max }})
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <Button type="submit" :disabled="processing">
                        Guardar
                    </Button>
                </div>
            </Form>
        </AdminSection>
    </div>
</template>
