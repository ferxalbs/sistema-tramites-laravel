import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import SupportWidget from '@/components/support-widget';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
            <SupportWidget />
        </>
    );
}
