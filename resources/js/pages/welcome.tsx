import { FormEvent, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, FileSearch, HelpCircle, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import SupportWidget from '@/components/support-widget';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const [quickCode, setQuickCode] = useState('');

    const handleQuickVerify = (e: FormEvent) => {
        e.preventDefault();
        const clean = quickCode.trim().toUpperCase();
        if (clean) {
            router.visit(`/verificar-documento?codigo=${encodeURIComponent(clean)}`);
        }
    };

    return (
        <>
            <Head title="Portal de Trámites y Gestión Documental" />

            <div className="relative min-h-screen flex flex-col bg-background text-foreground">
                {/* Subtle ambient lighting */}
                <div className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div className="absolute -top-32 left-1/2 -translate-x-1/2 h-[380px] w-[700px] rounded-full bg-primary/5 blur-3xl" />
                </div>

                {/* Top Navigation */}
                <header className="relative z-10 w-full border-b border-border/40 backdrop-blur-md bg-background/80">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-xs">
                                <AppLogoIcon className="size-5" />
                            </div>
                            <div className="flex flex-col">
                                <span className="text-sm font-semibold tracking-tight leading-tight">
                                    Sistema de Trámites
                                </span>
                                <span className="text-[11px] text-muted-foreground leading-tight">
                                    Gestión Documental
                                </span>
                            </div>
                        </Link>

                        <nav className="flex items-center gap-2 sm:gap-4">
                            <Link
                                href="/verificar-documento"
                                className="hidden sm:inline-flex text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Verificar Documento
                            </Link>
                            <Link
                                href="/ayuda"
                                className="hidden sm:inline-flex text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Centro de Ayuda
                            </Link>

                            <div className="flex items-center gap-2 pl-2 border-l border-border/60">
                                {auth.user ? (
                                    <Button size="sm" render={<Link href={dashboard()} />}>
                                        Ir al Panel
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            render={<Link href={login()} />}
                                        >
                                            Iniciar Sesión
                                        </Button>
                                        <Button
                                            size="sm"
                                            render={<Link href={register()} />}
                                        >
                                            Registrarse
                                        </Button>
                                    </>
                                )}
                            </div>
                        </nav>
                    </div>
                </header>

                {/* Main Hero & Content */}
                <main className="relative z-10 flex-1">
                    {/* Hero Section */}
                    <section className="mx-auto max-w-4xl px-4 pt-16 pb-12 sm:px-6 sm:pt-24 lg:px-8 text-center">
                        <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-medium text-primary mb-6">
                            <ShieldCheck className="size-3.5" />
                            <span>Portal Institucional de Atención y Trámites Digitales</span>
                        </div>

                        <h1 className="text-3xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl text-balance">
                            Gestión y seguimiento de tus trámites en un solo lugar
                        </h1>

                        <p className="mt-4 text-base sm:text-lg text-muted-foreground max-w-2xl mx-auto text-balance">
                            Registra solicitudes académicas y administrativas, consulta el avance en tiempo real y valida la autenticidad de resoluciones y constancias oficiales.
                        </p>

                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            {auth.user ? (
                                <Button
                                    size="lg"
                                    render={<Link href={dashboard()} />}
                                >
                                    Ingresar a mis Trámites
                                    <ArrowRight className="size-4" />
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        size="lg"
                                        render={<Link href={login()} />}
                                    >
                                        Iniciar Trámite
                                        <ArrowRight className="size-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        render={<Link href="/verificar-documento" />}
                                    >
                                        <ShieldCheck className="size-4 mr-1" />
                                        Verificar Documento
                                    </Button>
                                </>
                            )}
                        </div>

                        {/* Quick Document Search Bar */}
                        <div className="mt-12 mx-auto max-w-xl">
                            <Card className="border-border/80 shadow-sm bg-card/60 backdrop-blur-sm">
                                <CardHeader className="pb-3 text-left">
                                    <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                        <FileSearch className="size-4 text-primary" />
                                        Consulta rápida de autenticidad de documento
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Ingresa el código de verificación alfanumérico que figura en tu documento oficial
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleQuickVerify} className="flex gap-2">
                                        <Input
                                            type="text"
                                            value={quickCode}
                                            onChange={(e) => setQuickCode(e.target.value)}
                                            placeholder="Ej. A1B2-C3D4-E5F6-7890"
                                            className="font-mono uppercase text-xs sm:text-sm"
                                        />
                                        <Button type="submit" size="default">
                                            Validar
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    </section>

                    {/* Features / Benefits Grid */}
                    <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8 border-t border-border/40">
                        <div className="grid gap-6 sm:grid-cols-3">
                            <Card className="border-border/60 bg-card/50 hover:border-primary/40 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary mb-3">
                                        <CheckCircle2 className="size-5" />
                                    </div>
                                    <CardTitle className="text-base font-semibold">
                                        Seguimiento en Tiempo Real
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    Supervisa cada fase de tu expediente: registro, asignación de revisores, observaciones y aprobación final con total transparencia.
                                </CardContent>
                            </Card>

                            <Card className="border-border/60 bg-card/50 hover:border-primary/40 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary mb-3">
                                        <ShieldCheck className="size-5" />
                                    </div>
                                    <CardTitle className="text-base font-semibold">
                                        Verificación Pública con QR
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    Todos los documentos emitidos cuentan con código único de verificación accesible para cualquier entidad o ciudadano sin requerir cuenta.
                                </CardContent>
                            </Card>

                            <Card className="border-border/60 bg-card/50 hover:border-primary/40 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary mb-3">
                                        <HelpCircle className="size-5" />
                                    </div>
                                    <CardTitle className="text-base font-semibold">
                                        Mesa de Ayuda y Guías
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    Revisa requisitos, instructivos y preguntas frecuentes para completar tus gestiones de manera correcta y oportuna.
                                </CardContent>
                            </Card>
                        </div>
                    </section>
                </main>

                {/* Footer */}
                <footer className="relative z-10 border-t border-border/40 py-6 text-center text-xs text-muted-foreground">
                    <div className="mx-auto max-w-6xl px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-4 text-primary" />
                            <span>Sistema de Trámites y Gestión Documental</span>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link href="/verificar-documento" className="hover:underline">
                                Verificación Pública
                            </Link>
                            <Link href="/ayuda" className="hover:underline">
                                Mesa de Ayuda
                            </Link>
                        </div>
                    </div>
                </footer>
            </div>

            <SupportWidget />
        </>
    );
}
