<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    LayoutGrid,
    ListChecks,
    Settings,
    Shield,
    Ban,
    BookOpen,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
import * as appRoutes from '@/routes';
import adminAllowListRoutes from '@/routes/admin/allow-list';
import adminArtifactsRoutes from '@/routes/admin/artifacts';
import adminAuditRoutes from '@/routes/admin/audit';
import adminBlackoutsRoutes from '@/routes/admin/blackouts';
import adminFacultiesRoutes from '@/routes/admin/faculties';
import adminHistoryRoutes from '@/routes/admin/history';
import adminRequestsRoutes from '@/routes/admin/requests';
import adminSchoolsRoutes from '@/routes/admin/schools';
import adminSettingsRoutes from '@/routes/admin/settings';
import calendarRoutes from '@/routes/calendar';
import reservationsRoutes from '@/routes/reservations';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: appRoutes.dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Calendario',
        href: calendarRoutes.index(),
        icon: CalendarDays,
    },
    {
        title: 'Mis reservas',
        href: reservationsRoutes.index(),
        icon: ListChecks,
    },
    {
        title: 'Nueva solicitud',
        href: reservationsRoutes.create(),
        icon: CalendarDays,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Solicitudes',
        href: adminRequestsRoutes.index(),
        icon: Shield,
    },
    {
        title: 'Historial',
        href: adminHistoryRoutes.index(),
        icon: BookOpen,
    },
    {
        title: 'Configuración',
        href: adminSettingsRoutes.edit(),
        icon: Settings,
    },
    {
        title: 'Facultades',
        href: adminFacultiesRoutes.index(),
        icon: Shield,
    },
    {
        title: 'Escuelas',
        href: adminSchoolsRoutes.index(),
        icon: Shield,
    },
    {
        title: 'Allow-list',
        href: adminAllowListRoutes.index(),
        icon: ListChecks,
    },
    {
        title: 'Blackouts',
        href: adminBlackoutsRoutes.index(),
        icon: Ban,
    },
    {
        title: 'Auditoría',
        href: adminAuditRoutes.index(),
        icon: BookOpen,
    },
    {
        title: 'Reintentos',
        href: adminArtifactsRoutes.index(),
        icon: Shield,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="appRoutes.dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="isAdmin"
                :items="adminNavItems"
                label="Administración"
            />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
