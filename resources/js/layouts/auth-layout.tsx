import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import HelpTools from '@/components/help-tools';
import { home } from '@/routes';

export default function AuthLayout({
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-background px-4 py-8 sm:px-6 lg:px-8">
            {/* Ambient background decoration */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -top-40 left-1/2 h-[340px] w-[500px] -translate-x-1/2 rounded-full bg-primary/5 blur-3xl" />
            </div>

            <div className="relative z-10 flex w-full flex-col items-center gap-6">
                <Link
                    href={home()}
                    className="group inline-flex items-center gap-3 transition-opacity hover:opacity-90"
                >
                    <div className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm transition-transform duration-200 group-hover:scale-105">
                        <AppLogoIcon className="size-5" />
                    </div>
                    <div className="flex flex-col text-left">
                        <span className="text-base leading-tight font-semibold tracking-tight text-foreground">
                            Sistema de Trámites
                        </span>
                        <span className="text-xs leading-tight text-muted-foreground">
                            Gestión Documental
                        </span>
                    </div>
                </Link>

                <div className="flex w-full flex-col items-center">
                    {children}
                </div>
            </div>

            <HelpTools />
        </div>
    );
}
