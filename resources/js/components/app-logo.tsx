import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-9 shadow-xs ring-1 ring-primary/15" />
            <div className="ml-1 grid flex-1 text-left text-sm leading-none">
                <span className="truncate font-semibold tracking-tight">
                    {name || 'Sistema de Trámites'}
                </span>
                <span className="mt-0.5 truncate text-[11px] text-muted-foreground">
                    Desarrollo de Sistemas de Información
                </span>
            </div>
        </>
    );
}
