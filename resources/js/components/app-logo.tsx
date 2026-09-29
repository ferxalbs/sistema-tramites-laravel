import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs">
                <AppLogoIcon className="size-4.5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm leading-none">
                <span className="truncate font-semibold tracking-tight">
                    {name || 'Sistema de Trámites'}
                </span>
                <span className="text-[11px] text-muted-foreground truncate mt-0.5">
                    Gestión Institucional
                </span>
            </div>
        </>
    );
}
