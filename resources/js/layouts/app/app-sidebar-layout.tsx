import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="flex h-svh max-h-svh flex-col overflow-hidden"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <ScrollArea className="min-h-0 w-full flex-1">
                    <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                        {children}
                    </div>
                </ScrollArea>
            </AppContent>
        </AppShell>
    );
}
