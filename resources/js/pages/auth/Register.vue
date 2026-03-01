<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import * as appRoutes from '@/routes';
import registerRoutes from '@/routes/register';

defineOptions({ layout: false });

type ProfessionalSchool = {
    id: number;
    faculty_id: number;
    name: string;
    base_year_min: number;
    base_year_max: number;
};

type Faculty = {
    id: number;
    name: string;
    professional_schools: ProfessionalSchool[];
};

const props = defineProps<{
    faculties: Faculty[];
}>();

const selectedFacultyId = ref<string>(
    props.faculties[0] ? String(props.faculties[0].id) : '',
);
const selectedSchoolId = ref<string>('');
const selectedBaseYear = ref<string>('');

const availableSchools = computed<ProfessionalSchool[]>(() => {
    const facultyId = Number(selectedFacultyId.value);
    if (!Number.isFinite(facultyId) || facultyId <= 0) {
        return [];
    }

    const faculty = props.faculties.find((f) => f.id === facultyId);
    return faculty?.professional_schools ?? [];
});

const selectedSchool = computed<ProfessionalSchool | null>(() => {
    const schoolId = Number(selectedSchoolId.value);
    if (!Number.isFinite(schoolId) || schoolId <= 0) {
        return null;
    }

    return availableSchools.value.find((s) => s.id === schoolId) ?? null;
});

const baseYears = computed<number[]>(() => {
    if (!selectedSchool.value) {
        return [];
    }

    const years: number[] = [];
    for (
        let y = selectedSchool.value.base_year_min;
        y <= selectedSchool.value.base_year_max;
        y += 1
    ) {
        years.push(y);
    }

    return years;
});

const baseLabel = (year: number): string => {
    const yy = String(year % 100).padStart(2, '0');
    return `B${yy}`;
};

watch(selectedFacultyId, () => {
    selectedSchoolId.value = '';
    selectedBaseYear.value = '';
});

watch(selectedSchoolId, () => {
    selectedBaseYear.value = '';
});
</script>

<template>
    <AuthBase
        title="Crear cuenta"
        description="Ingresa tus datos para registrarte"
    >
        <Head title="Registro" />

        <Form
            v-bind="registerRoutes.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">Nombres</Label>
                        <Input
                            id="first_name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="given-name"
                            name="first_name"
                            placeholder="Tus nombres"
                        />
                        <InputError :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Apellidos</Label>
                        <Input
                            id="last_name"
                            type="text"
                            required
                            :tabindex="2"
                            autocomplete="family-name"
                            name="last_name"
                            placeholder="Tus apellidos"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="faculty">Facultad</Label>
                        <NativeSelect
                            id="faculty"
                            v-model="selectedFacultyId"
                            :tabindex="3"
                        >
                            <option value="" disabled>
                                Selecciona una facultad
                            </option>
                            <option
                                v-for="f in props.faculties"
                                :key="f.id"
                                :value="String(f.id)"
                            >
                                {{ f.name }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="grid gap-2">
                        <Label for="professional_school_id"
                            >Escuela profesional</Label
                        >
                        <NativeSelect
                            id="professional_school_id"
                            v-model="selectedSchoolId"
                            name="professional_school_id"
                            required
                            :tabindex="4"
                            :disabled="selectedFacultyId === ''"
                        >
                            <option value="" disabled>
                                Selecciona una escuela
                            </option>
                            <option
                                v-for="s in availableSchools"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.name }}
                            </option>
                        </NativeSelect>
                        <InputError :message="errors.professional_school_id" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="base_year">Base (año)</Label>
                    <NativeSelect
                        id="base_year"
                        v-model="selectedBaseYear"
                        name="base_year"
                        required
                        :tabindex="5"
                        :disabled="selectedSchool === null"
                    >
                        <option value="" disabled>Selecciona una base</option>
                        <option
                            v-for="y in baseYears"
                            :key="y"
                            :value="String(y)"
                        >
                            {{ baseLabel(y) }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.base_year" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Teléfono (opcional)</Label>
                    <Input
                        id="phone"
                        type="tel"
                        :tabindex="6"
                        autocomplete="tel"
                        name="phone"
                        placeholder="9XXXXXXXX"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo institucional</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="7"
                        autocomplete="email"
                        name="email"
                        placeholder="usuario@unmsm.edu.pe"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="8"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Contraseña"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation"
                        >Confirmar contraseña</Label
                    >
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="9"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmar contraseña"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="10"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes una cuenta?
                <TextLink
                    :href="appRoutes.login()"
                    class="underline underline-offset-4"
                    :tabindex="11"
                    >Iniciar sesión</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
