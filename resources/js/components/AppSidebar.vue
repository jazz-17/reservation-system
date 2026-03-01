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

const permissions = computed(() => {
    const user = page.props.auth?.user;
    if (!user) {
        return [];
    }

    return Array.isArray(user.permissions) ? user.permissions : [];
});

const hasPermission = (permission: string) =>
    permissions.value.includes(permission);

const navItemsFor = (items: Array<{ permission: string; item: NavItem }>) =>
    items.filter(({ permission }) => hasPermission(permission)).map(({ item }) => item);

const reservasNavItems = computed<NavItem[]>(() =>
    navItemsFor([
        {
            permission: 'admin.reservas.solicitudes.view',
            item: {
                title: 'Solicitudes',
                href: adminRequestsRoutes.index(),
                icon: Shield,
            },
        },
        {
            permission: 'admin.reservas.historial.view',
            item: {
                title: 'Historial',
                href: adminHistoryRoutes.index(),
                icon: BookOpen,
            },
        },
        {
            permission: 'admin.reservas.reintentos.view',
            item: {
                title: 'Reintentos',
                href: adminArtifactsRoutes.index(),
                icon: Shield,
            },
        },
    ]),
);

const gestionNavItems = computed<NavItem[]>(() =>
    navItemsFor([
        {
            permission: 'admin.gestion.configuracion.manage',
            item: {
                title: 'Configuración',
                href: adminSettingsRoutes.edit(),
                icon: Settings,
            },
        },
        {
            permission: 'admin.gestion.facultades.manage',
            item: {
                title: 'Facultades',
                href: adminFacultiesRoutes.index(),
                icon: Shield,
            },
        },
        {
            permission: 'admin.gestion.escuelas.manage',
            item: {
                title: 'Escuelas',
                href: adminSchoolsRoutes.index(),
                icon: Shield,
            },
        },
        {
            permission: 'admin.gestion.allow_list.view',
            item: {
                title: 'Allow-list',
                href: adminAllowListRoutes.index(),
                icon: ListChecks,
            },
        },
        {
            permission: 'admin.gestion.blackouts.manage',
            item: {
                title: 'Blackouts',
                href: adminBlackoutsRoutes.index(),
                icon: Ban,
            },
        },
    ]),
);

const supervisionNavItems = computed<NavItem[]>(() =>
    navItemsFor([
        {
            permission: 'admin.supervision.auditoria.view',
            item: {
                title: 'Auditoría',
                href: adminAuditRoutes.index(),
                icon: BookOpen,
            },
        },
    ]),
);

const canAccessAdmin = computed(() => hasPermission('admin.panel.access'));

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
                v-if="canAccessAdmin && reservasNavItems.length > 0"
                :items="reservasNavItems"
                label="Reservas"
            />
            <NavMain
                v-if="canAccessAdmin && gestionNavItems.length > 0"
                :items="gestionNavItems"
                label="Gestión"
            />
            <NavMain
                v-if="canAccessAdmin && supervisionNavItems.length > 0"
                :items="supervisionNavItems"
                label="Supervisión"
            />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
