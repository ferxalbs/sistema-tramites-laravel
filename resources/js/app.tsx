import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { logoutHistoryGuardKey } from '@/lib/auth-history';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

if (typeof window !== 'undefined') {
    window.addEventListener('pageshow', (event) => {
        if (
            !event.persisted ||
            window.sessionStorage.getItem(logoutHistoryGuardKey) !== '1'
        ) {
            return;
        }

        window.location.reload();
    });

    window.addEventListener(
        'popstate',
        (event) => {
            if (window.sessionStorage.getItem(logoutHistoryGuardKey) !== '1') {
                return;
            }

            event.stopImmediatePropagation();
            window.location.reload();
        },
        true,
    );

    router.on('navigate', (event) => {
        const auth = event.detail.page.props.auth as
            | { user?: unknown }
            | undefined;

        if (auth?.user) {
            window.sessionStorage.removeItem(logoutHistoryGuardKey);
        }
    });
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome' ||
                name === 'ayuda' ||
                name === 'tramites/comprobante' ||
                name === 'documentos/verificacion-publica':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delay={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
