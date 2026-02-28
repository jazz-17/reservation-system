<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable, show } from '@/routes/two-factor';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({ layout: [AppLayout, SettingsLayout] });

useBreadcrumbs([
    {
        title: 'Autenticación de dos factores',
        href: show.url(),
    },
]);

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <Head title="Autenticación de dos factores" />

    <h1 class="sr-only">Configuración de autenticación de dos factores</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Autenticación de dos factores"
            description="Administra la configuración de 2FA"
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <Badge variant="destructive">Desactivado</Badge>

            <p class="text-muted-foreground">
                Al activar 2FA, se te pedirá un código durante el inicio de
                sesión. Este código se obtiene desde una aplicación
                autenticadora compatible con TOTP en tu celular.
            </p>

            <div>
                <Button v-if="hasSetupData" @click="showSetupModal = true">
                    <ShieldCheck />Continuar configuración
                </Button>
                <Form
                    v-else
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                    #default="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        <ShieldCheck />Activar 2FA</Button
                    ></Form
                >
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <Badge variant="default">Activado</Badge>

            <p class="text-muted-foreground">
                Con 2FA activado, se te pedirá un código durante el inicio de
                sesión. Obtén el código desde tu aplicación autenticadora
                (TOTP).
            </p>

            <TwoFactorRecoveryCodes />

            <div class="relative inline">
                <Form v-bind="disable.form()" #default="{ processing }">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        <ShieldBan />
                        Desactivar 2FA
                    </Button>
                </Form>
            </div>
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>
</template>
