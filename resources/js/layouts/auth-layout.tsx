import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceSwitch from '@/components/appearance-switch';
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
        <div className="min-h-svh bg-background text-foreground lg:grid lg:grid-cols-[minmax(380px,42%)_1fr]">
            <aside className="relative hidden min-h-svh flex-col justify-between overflow-hidden bg-[#3b0a12] p-10 text-white lg:flex xl:p-14">
                <div className="pointer-events-none absolute -top-32 -left-32 size-96 rounded-full bg-white/5 blur-3xl" />
                <Link
                    href={home()}
                    className="relative z-10 inline-flex items-center gap-3 self-start transition-opacity hover:opacity-85"
                >
                    <AppLogoIcon className="size-11 ring-1 ring-white/20" />
                    <span className="text-sm leading-tight font-semibold">
                        Sistema de Trámites
                        <span className="mt-1 block text-xs font-normal text-white/75">
                            Desarrollo de Sistemas de Información
                        </span>
                    </span>
                </Link>

                <div className="relative z-10 mx-auto max-w-md text-center">
                    <img
                        src="/brand/dsi-seoane.jpg"
                        alt="Logo de Desarrollo de Sistemas de Información del IESTP Manuel Seoane Corrales"
                        className="mx-auto mb-7 size-64 rounded-3xl object-cover shadow-2xl ring-1 ring-white/15 xl:size-72"
                    />
                    <h1 className="text-3xl font-bold tracking-tight">
                        Tus trámites, en un solo lugar
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-white/80">
                        Gestión y seguimiento de solicitudes del área de
                        Desarrollo de Sistemas de Información.
                    </p>
                </div>

                <p className="relative z-10 text-xs text-white/65">
                    I.E.S.T.P. Manuel Seoane Corrales
                </p>
            </aside>

            <div className="relative flex min-h-svh flex-col items-center justify-center px-4 py-16 sm:px-6 lg:px-10">
                <AppearanceSwitch className="absolute top-4 right-4 sm:right-6 lg:right-10" />
                <Link
                    href={home()}
                    className="mb-8 inline-flex items-center gap-3 transition-opacity hover:opacity-85 lg:hidden"
                >
                    <AppLogoIcon className="size-12 ring-1 ring-border" />
                    <span className="text-sm leading-tight font-semibold">
                        Sistema de Trámites
                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                            Desarrollo de Sistemas de Información
                        </span>
                    </span>
                </Link>

                <div className="flex w-full max-w-lg flex-col items-center">
                    {children}
                </div>
            </div>

            <HelpTools />
        </div>
    );
}
