<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    Folder,
    LayoutGrid,
    ListChecks,
    Settings,
    Shield,
    Ban,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminAllowList } from '@/routes/admin/allow-list';
import { index as adminArtifacts } from '@/routes/admin/artifacts';
import { index as adminAudit } from '@/routes/admin/audit';
import { index as adminBlackouts } from '@/routes/admin/blackouts';
import { index as adminHistory } from '@/routes/admin/history';
import { index as adminRequests } from '@/routes/admin/requests';
import { edit as adminSettings } from '@/routes/admin/settings';
import { publicMethod as publicCalendar } from '@/routes/calendar';
import {
    index as reservationsIndex,
    create as reservationsCreate,
} from '@/routes/reservations';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Calendario',
            href: publicCalendar(),
            icon: CalendarDays,
        },
        {
            title: 'Mis reservas',
            href: reservationsIndex(),
            icon: ListChecks,
        },
        {
            title: 'Nueva solicitud',
            href: reservationsCreate(),
            icon: CalendarDays,
        },
    ];

    if (isAdmin.value) {
        items.push(
            {
                title: 'Admin — Solicitudes',
                href: adminRequests(),
                icon: Shield,
            },
            {
                title: 'Admin — Historial',
                href: adminHistory(),
                icon: BookOpen,
            },
            {
                title: 'Admin — Configuración',
                href: adminSettings(),
                icon: Settings,
            },
            {
                title: 'Admin — Allow-list',
                href: adminAllowList(),
                icon: ListChecks,
            },
            {
                title: 'Admin — Blackouts',
                href: adminBlackouts(),
                icon: Ban,
            },
            {
                title: 'Admin — Auditoría',
                href: adminAudit(),
                icon: BookOpen,
            },
            {
                title: 'Admin — Reintentos',
                href: adminArtifacts(),
                icon: Shield,
            },
        );
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentación',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
