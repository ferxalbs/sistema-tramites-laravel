import { FormEvent, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    FileCheck2,
    FileSearch,
    FileText,
    HelpCircle,
    QrCode,
    Search,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import SupportWidget from '@/components/support-widget';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
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

            <ScrollArea className="h-screen w-full">
                <div className="flex min-h-screen flex-col bg-background text-foreground">
                    {/* Top Navigation Bar */}
                    <header className="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur-xs">
                        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                            <Link href="/" className="flex items-center gap-3">
                                <div className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs">
                                    <AppLogoIcon className="size-5" />
                                </div>
                                <div className="flex flex-col text-left">
                                    <span className="text-sm font-semibold tracking-tight">
                                        Sistema de Trámites
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Gestión Documental Institucional
                                    </span>
                                </div>
                            </Link>

                            <nav className="flex items-center gap-4">
                                <div className="hidden md:flex items-center gap-6 text-sm">
                                    <Link
                                        href="/verificar-documento"
                                        className="text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        Verificar Documento
                                    </Link>
                                    <Link
                                        href="/ayuda"
                                        className="text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        Centro de Ayuda
                                    </Link>
                                </div>

                                <Separator orientation="vertical" className="hidden md:block h-6" />

                                <div className="flex items-center gap-2">
                                    {auth.user ? (
                                        <Button render={<Link href={dashboard()} />}>
                                            Ir al Panel
                                            <ArrowRight />
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                variant="ghost"
                                                render={<Link href={login()} />}
                                            >
                                                Iniciar Sesión
                                            </Button>
                                            <Button
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

                    {/* Main Content Area */}
                    <main className="flex-1">
                        {/* Hero Section: Spacious 2-Column Desktop Layout */}
                        <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                            <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-8">
                                {/* Left Column: Headline, Description, Primary CTAs & Value Props */}
                                <div className="flex flex-col items-start gap-6 lg:col-span-7">
                                    <Badge variant="secondary" className="gap-2 py-1 px-3">
                                        <ShieldCheck className="size-4 text-primary" />
                                        <span>Portal Oficial de Trámites y Gestión Documental</span>
                                    </Badge>

                                    <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl text-foreground text-balance">
                                        Gestión y seguimiento de tus trámites en un solo lugar
                                    </h1>

                                    <p className="max-w-2xl text-lg sm:text-xl text-muted-foreground text-balance leading-relaxed">
                                        Presenta tus documentos en Mesa de Partes. El personal registra tu expediente para que puedas consultar su avance y verificar la autenticidad del documento oficial.
                                    </p>

                                    <div className="flex flex-wrap items-center gap-4 pt-2">
                                        {auth.user ? (
                                            <Button
                                                size="lg"
                                                render={<Link href={dashboard()} />}
                                            >
                                                Acceder a mis Trámites
                                                <ArrowRight />
                                            </Button>
                                        ) : (
                                            <>
                                                <Button
                                                    size="lg"
                                                    render={<Link href={login()} />}
                                                >
                                                    Consultar mis Expedientes
                                                    <ArrowRight />
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="lg"
                                                    render={<Link href="/ayuda" />}
                                                >
                                                    Centro de Ayuda
                                                </Button>
                                            </>
                                        )}
                                    </div>

                                    {/* Highlights Row */}
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-6 border-t w-full text-left">
                                        <div className="flex flex-col gap-1.5">
                                            <span className="text-sm font-semibold text-foreground flex items-center gap-2">
                                                <FileCheck2 className="size-4 text-primary" />
                                                Recepción Presencial
                                            </span>
                                            <span className="text-xs text-muted-foreground leading-normal">
                                                Entrega tus documentos en Mesa de Partes.
                                            </span>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <span className="text-sm font-semibold text-foreground flex items-center gap-2">
                                                <Clock className="size-4 text-primary" />
                                                En Tiempo Real
                                            </span>
                                            <span className="text-xs text-muted-foreground leading-normal">
                                                Alertas y seguimiento del avance de tu trámite.
                                            </span>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <span className="text-sm font-semibold text-foreground flex items-center gap-2">
                                                <QrCode className="size-4 text-primary" />
                                                Validación QR
                                            </span>
                                            <span className="text-xs text-muted-foreground leading-normal">
                                                Comprobación pública de autenticidad documental.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Right Column: Prominent Quick Verification Card */}
                                <div className="lg:col-span-5">
                                    <Card className="w-full">
                                        <CardHeader>
                                            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary mb-2">
                                                <FileSearch className="size-5" />
                                            </div>
                                            <CardTitle className="text-xl">Verificación de Documento</CardTitle>
                                            <CardDescription className="text-sm leading-relaxed">
                                                Comprueba de manera inmediata la autenticidad y validez oficial de resoluciones, constancias o certificados institucionales.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <form onSubmit={handleQuickVerify} className="flex flex-col gap-4">
                                                <div className="flex flex-col gap-2">
                                                    <span className="text-xs font-medium text-foreground">
                                                        Código de Verificación Alfanumérico
                                                    </span>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            type="text"
                                                            value={quickCode}
                                                            onChange={(e) => setQuickCode(e.target.value)}
                                                            placeholder="Ej. A1B2-C3D4-E5F6-7890"
                                                            className="font-mono uppercase text-sm"
                                                            required
                                                        />
                                                        <Button type="submit">
                                                            <Search className="size-4" />
                                                            Validar
                                                        </Button>
                                                    </div>
                                                </div>
                                                <p className="text-xs text-muted-foreground leading-normal">
                                                    El código figura en el pie de página o bajo el código QR de todo documento emitido oficialmente.
                                                </p>
                                            </form>
                                        </CardContent>
                                        <CardFooter className="justify-between text-xs text-muted-foreground border-t pt-4">
                                            <span>Servicio de consulta ciudadana</span>
                                            <Link
                                                href="/verificar-documento"
                                                className="text-primary hover:underline font-medium"
                                            >
                                                Módulo completo →
                                            </Link>
                                        </CardFooter>
                                    </Card>
                                </div>
                            </div>
                        </section>

                        <Separator className="mx-auto max-w-7xl" />

                        {/* Services Grid Section */}
                        <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                            <div className="mb-12 flex flex-col gap-2 text-left">
                                <Badge variant="outline" className="w-fit gap-1.5">
                                    <Sparkles className="size-3.5 text-primary" />
                                    <span>Servicios Integrados</span>
                                </Badge>
                                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">
                                    Servicios del Sistema de Trámites
                                </h2>
                                <p className="max-w-3xl text-base text-muted-foreground leading-relaxed">
                                    Herramientas diseñadas para agilizar la gestión de solicitudes académicas y administrativas con total trazabilidad.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <Card>
                                    <CardHeader>
                                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary mb-3">
                                            <Clock className="size-5" />
                                        </div>
                                        <CardTitle className="text-lg">Seguimiento Continuo</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Conoce la etapa exacta de tu expediente: derivación entre oficinas, dictámenes técnicos y emisión del documento final con plazos transparentes.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary mb-3">
                                            <QrCode className="size-5" />
                                        </div>
                                        <CardTitle className="text-lg">Autenticación con QR</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Cada documento generado cuenta con un identificador único y firma institucional accesible para verificación pública sin autenticación previa.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary mb-3">
                                            <HelpCircle className="size-5" />
                                        </div>
                                        <CardTitle className="text-lg">Mesa de Ayuda y Guías</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Accede a requisitos actualizados, preguntas frecuentes y canales de soporte directo ante cualquier duda o contingencia en tu trámite.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            </div>
                        </section>

                        <Separator className="mx-auto max-w-7xl" />

                        {/* Step-by-Step Workflow Section */}
                        <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                            <div className="mb-12 flex flex-col gap-2 text-left">
                                <Badge variant="outline" className="w-fit">
                                    Flujo de Trabajo
                                </Badge>
                                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">
                                    ¿Cómo se gestiona tu trámite?
                                </h2>
                                <p className="max-w-2xl text-base text-muted-foreground leading-relaxed">
                                    La recepción presencial inicia un proceso documentado y trazable.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                <Card>
                                    <CardHeader>
                                        <div className="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground mb-2">
                                            1
                                        </div>
                                        <CardTitle className="text-base">Presenta tus Documentos</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Entrega los requisitos en Mesa de Partes. El personal interno registra tu solicitud y te asigna un expediente.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <div className="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground mb-2">
                                            2
                                        </div>
                                        <CardTitle className="text-base">Revisión y Dictamen</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Las unidades responsables revisan tu solicitud y emiten observaciones o aprobación conforme a plazos normativos.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <div className="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground mb-2">
                                            3
                                        </div>
                                        <CardTitle className="text-base">Entrega y Confirmación</CardTitle>
                                        <CardDescription className="text-sm leading-relaxed">
                                            Recibe el documento por el canal institucional y confirma su recepción. El código QR permite verificar su autenticidad.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            </div>
                        </section>
                    </main>

                    {/* Footer */}
                    <footer className="border-t py-10 text-sm text-muted-foreground">
                        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6 lg:px-8">
                            <div className="flex items-center gap-2">
                                <AppLogoIcon className="size-4 text-primary" />
                                <span>Sistema de Trámites y Gestión Documental</span>
                            </div>
                            <div className="flex items-center gap-6">
                                <Link
                                    href="/verificar-documento"
                                    className="hover:text-foreground transition-colors"
                                >
                                    Verificación Pública
                                </Link>
                                <Link
                                    href="/ayuda"
                                    className="hover:text-foreground transition-colors"
                                >
                                    Centro de Ayuda
                                </Link>
                            </div>
                        </div>
                    </footer>
                </div>
            </ScrollArea>

            <SupportWidget />
        </>
    );
}
