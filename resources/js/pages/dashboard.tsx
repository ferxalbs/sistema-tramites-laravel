import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ClipboardList, FileCheck, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Mis trámites</CardTitle>
                            <CardDescription>
                                Consulta y da seguimiento a tus trámites activos.
                            </CardDescription>
                            <CardAction>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    render={
                                        <Link
                                            href={TramiteEstudianteController.index()}
                                        />
                                    }
                                >
                                    <ClipboardList />
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full justify-between"
                                render={
                                    <Link
                                        href={TramiteEstudianteController.index()}
                                    />
                                }
                            >
                                <span>Ver listado</span>
                                <ArrowRight />
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estado de solicitudes</CardTitle>
                            <CardDescription>
                                Revisa el progreso en tiempo real de cada etapa.
                            </CardDescription>
                            <CardAction>
                                <Button variant="ghost" size="icon" disabled>
                                    <FileCheck />
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Los trámites son procesados y revisados por el
                                personal docente y administrativo.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sistema institucional</CardTitle>
                            <CardDescription>
                                Gestión digital y entrega de documentación.
                            </CardDescription>
                            <CardAction>
                                <Button variant="ghost" size="icon" disabled>
                                    <Layers />
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Plataforma de seguimiento y control de trámites
                                académicos y administrativos.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="flex-1">
                    <CardHeader>
                        <CardTitle>Bienvenido al Sistema de Trámites</CardTitle>
                        <CardDescription>
                            Utiliza la barra lateral para navegar entre las
                            diferentes secciones según tu rol asignado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm text-muted-foreground">
                        <p>
                            Desde este panel podrás acceder a los módulos de
                            gestión de trámites, historial de documentos,
                            revisiones y asignaciones pertinentes.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
