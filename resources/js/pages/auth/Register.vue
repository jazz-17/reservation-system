<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import * as appRoutes from '@/routes';
import registerRoutes from '@/routes/register';

defineOptions({ layout: false });
</script>

<template>
    <AuthBase
        title="Crear cuenta"
        description="Regístrate con tu correo institucional"
    >
        <Head title="Registro" />

        <Form
            v-bind="registerRoutes.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div
                    class="rounded-md border border-border/60 bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                >
                    Tu escuela y base se asignan automáticamente según tu
                    registro institucional.
                </div>

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

                <div class="grid gap-2">
                    <Label for="phone">Teléfono (opcional)</Label>
                    <Input
                        id="phone"
                        type="tel"
                        :tabindex="3"
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
                        :tabindex="4"
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
                        :tabindex="5"
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
                        :tabindex="6"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmar contraseña"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="7"
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
                    :tabindex="8"
                    >Iniciar sesión</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
