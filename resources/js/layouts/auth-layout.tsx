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
        <div className="relative min-h-svh flex flex-col items-center justify-center bg-background px-4 py-8 sm:px-6 lg:px-8">
            {/* Ambient background decoration */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -top-40 left-1/2 -translate-x-1/2 h-[340px] w-[500px] rounded-full bg-primary/5 blur-3xl" />
            </div>

            <div className="relative z-10 w-full max-w-md flex flex-col items-center gap-6">
                <Link
                    href={home()}
                    className="group inline-flex items-center gap-3 transition-opacity hover:opacity-90"
                >
                    <div className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm group-hover:scale-105 transition-transform duration-200">
                        <AppLogoIcon className="size-5" />
                    </div>
                    <div className="flex flex-col text-left">
                        <span className="text-base font-semibold tracking-tight text-foreground leading-tight">
                            Sistema de Trámites
                        </span>
                        <span className="text-xs text-muted-foreground leading-tight">
                            Gestión Documental
                        </span>
                    </div>
                </Link>

                <div className="w-full">
                    {children}
                </div>
            </div>

            <HelpTools />
        </div>
    );
}
