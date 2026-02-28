<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import * as appRoutes from '@/routes';

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <header
            class="border-b border-border/60 bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4"
            >
                <div class="text-sm font-medium">
                    {{ page.props.name ?? 'Sistema de Reservas' }}
                </div>

                <nav class="flex items-center gap-3 text-sm">
                    <template v-if="page.props.auth?.user">
                        <Link
                            :href="appRoutes.dashboard()"
                            class="underline underline-offset-4"
                            >Dashboard</Link
                        >
                    </template>
                    <template v-else>
                        <Link
                            :href="appRoutes.login()"
                            class="underline underline-offset-4"
                            >Iniciar sesión</Link
                        >
                        <Link
                            :href="appRoutes.register()"
                            class="underline underline-offset-4"
                            >Registrarse</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <slot />
        </main>
    </div>
</template>
