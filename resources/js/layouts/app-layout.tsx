import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import HelpTools from '@/components/help-tools';
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
            <HelpTools />
        </>
    );
}
