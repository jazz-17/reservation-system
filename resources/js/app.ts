import { createInertiaApp } from '@inertiajs/vue3';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import AppLayout from './layouts/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        const queryClient = new QueryClient({
            defaultOptions: {
                queries: {
                    staleTime: 30_000,
                    retry: 1,
                    refetchOnWindowFocus: false,
                },
            },
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VueQueryPlugin, { queryClient })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
