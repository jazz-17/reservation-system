<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import * as appRoutes from '@/routes';
import verificationRoutes from '@/routes/verification';

defineOptions({ layout: false });

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Verificar correo"
        description="Verifica tu correo haciendo clic en el enlace que te enviamos."
    >
        <Head title="Verificación de correo" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-success"
        >
            Se envió un nuevo enlace de verificación al correo que registraste.
        </div>

        <Form
            v-bind="verificationRoutes.send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                Reenviar correo de verificación
            </Button>

            <TextLink
                :href="appRoutes.logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Cerrar sesión
            </TextLink>
        </Form>
    </AuthLayout>
</template>
