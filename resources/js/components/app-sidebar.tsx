import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    CircleHelp,
    ClipboardCheck,
    ClipboardList,
    PackageCheck,
    FileSpreadsheet,
    FolderGit2,
    LayoutGrid,
    Plus,
    Search,
    ShieldCheck,
    Users,
} from 'lucide-react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import TramiteEntregaAdminController from '@/actions/App/Http/Controllers/TramiteEntregaAdminController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { index as usersIndex } from '@/routes/admin/users';
import { index as holidaysIndex } from '@/routes/admin/holidays';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as searchIndex } from '@/routes/search';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as supportIndex } from '@/routes/support';
import type { Auth, NavItem } from '@/types';

type PageProps = {
    auth: Auth;
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (['asistente', 'administrador'].includes(auth.user.rol)) {
        mainNavItems.push(
            {
                title: 'Bandeja de trámites',
                href: TramiteController.index(),
                icon: ClipboardList,
            },
            {
                title: 'Registrar trámite',
                href: TramiteController.create(),
                icon: Plus,
            },
            {
                title: 'Reportes',
                href: reportsIndex(),
                icon: FileSpreadsheet,
            },
        );
    }

    if (auth.user.rol === 'asistente') {
        mainNavItems.push({
            title: 'Asignaciones',
            href: TramiteAsignacionController.index(),
            icon: ClipboardCheck,
        });
    }

    if (auth.user.rol === 'docente') {
        mainNavItems.push({
            title: 'Mis asignaciones',
            href: TramiteAsignacionController.docenteIndex(),
            icon: ClipboardCheck,
        });
    }

    if (auth.user.rol === 'administrador') {
        mainNavItems.push({
            title: 'Entregas y cierres',
            href: TramiteEntregaAdminController.index(),
            icon: PackageCheck,
        });
        mainNavItems.push({
            title: 'Revisión de oficina',
            href: TramiteAsignacionController.oficinaIndex(),
            icon: ClipboardCheck,
        });
        mainNavItems.push({
            title: 'Usuarios',
            href: usersIndex(),
            icon: Users,
        });
        mainNavItems.push({
            title: 'Feriados',
            href: holidaysIndex(),
            icon: CalendarDays,
        });
        mainNavItems.push({
            title: 'Auditoría',
            href: auditIndex(),
            icon: ShieldCheck,
        });
    }

    if (auth.user.rol === 'estudiante') {
        mainNavItems.push({
            title: 'Mis trámites',
            href: TramiteEstudianteController.index(),
            icon: ClipboardList,
        });
    }

    if (['asistente', 'docente', 'administrador'].includes(auth.user.rol)) {
        mainNavItems.push({
            title: 'Buscar',
            href: searchIndex(),
            icon: Search,
        });
    }

    mainNavItems.push({
        title: 'Ayuda',
        href: supportIndex(),
        icon: CircleHelp,
    });

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            render={<Link href={dashboard()} prefetch />}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
