import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Bell,
    BriefcaseBusiness,
    CircleHelp,
    ClipboardCheck,
    ClipboardList,
    PackageCheck,
    FileSpreadsheet,
    FileText,
    FolderGit2,
    LayoutGrid,
    Plus,
    Search,
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
import { index as positionsIndex } from '@/routes/admin/positions';
import { index as typesIndex } from '@/routes/admin/types';
import { index as classificationsIndex } from '@/routes/admin/classifications';
import { index as outputFormatsIndex } from '@/routes/admin/output-formats';
import { index as templatesIndex } from '@/routes/admin/templates';
import { index as searchIndex } from '@/routes/search';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as supportIndex } from '@/routes/support';
import { index as notificationsIndex } from '@/routes/notificaciones';
import { index as studentsIndex } from '@/routes/assistant/students';
import type { Auth, NavItem } from '@/types';

type PageProps = {
    auth: Auth;
    notificationUnreadCount: number;
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
    const { auth, notificationUnreadCount } = usePage<PageProps>().props;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    mainNavItems.push({
        title:
            notificationUnreadCount > 0
                ? `Notificaciones (${notificationUnreadCount})`
                : 'Notificaciones',
        href: notificationsIndex(),
        icon: Bell,
    });

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
            title: 'Estudiantes y egresados',
            href: studentsIndex(),
            icon: Users,
        });
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
            title: 'Cargos institucionales',
            href: positionsIndex(),
            icon: BriefcaseBusiness,
        });
        mainNavItems.push({
            title: 'Tipos de trámite',
            href: typesIndex(),
            icon: ClipboardList,
        });
        mainNavItems.push({
            title: 'Clasificaciones',
            href: classificationsIndex(),
            icon: FolderGit2,
        });
        mainNavItems.push({
            title: 'Formatos de salida',
            href: outputFormatsIndex(),
            icon: FileText,
        });
        mainNavItems.push({
            title: 'Plantillas documentales',
            href: templatesIndex(),
            icon: FileText,
        });
    }

    if (auth.user.rol === 'estudiante') {
        mainNavItems.push({
            title: 'Mis trámites',
            href: TramiteEstudianteController.index(),
            icon: ClipboardList,
        });
    }

    if (['estudiante', 'asistente', 'docente', 'administrador'].includes(auth.user.rol)) {
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
