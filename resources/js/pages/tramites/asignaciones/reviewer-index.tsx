import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ClipboardCheck } from 'lucide-react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Assignment = {
    tramite_id: number;
    codigo: string;
    asunto: string;
    persona_nombre: string | null;
    prioridad: string;
    fecha_asignacion: string | null;
    fecha_esperada: string | null;
    estado: string;
    estado_label: string;
    plantilla: string | null;
};

type Props = {
    destino: 'docente' | 'oficina';
    destino_label: string;
    asignaciones: { data: Assignment[] };
    filters: { estado: string };
};

export default function ReviewerIndex({
    destino,
    destino_label,
    asignaciones,
}: Props) {
    const show = (tramiteId: number) =>
        destino === 'docente'
            ? TramiteAsignacionController.docenteShow({ tramite: tramiteId })
            : TramiteAsignacionController.oficinaShow({ tramite: tramiteId });

    return (
        <>
            <Head title={`Asignaciones · ${destino_label}`} />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    {destino === 'oficina' && (
                        <p className="text-sm font-medium text-amber-700 dark:text-amber-300">
                            Bandeja formal de revisor, separada de la
                            administración general
                        </p>
                    )}
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {destino === 'docente'
                            ? 'Mis asignaciones'
                            : 'Revisión de oficina'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Trámites asignados a tu cuenta como revisor de{' '}
                        {destino_label.toLowerCase()}.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardCheck className="size-5" /> Trámites
                            recibidos
                        </CardTitle>
                        <CardDescription>
                            Solo aparecen las asignaciones activas dirigidas a
                            tu cuenta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {asignaciones.data.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No tienes trámites asignados actualmente.
                            </div>
                        ) : (
                            asignaciones.data.map((asignacion) => (
                                <article
                                    key={asignacion.tramite_id}
                                    className="grid gap-4 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">
                                                {asignacion.codigo}
                                            </span>
                                            <TramiteStatusBadge
                                                estado={asignacion.estado}
                                                label={asignacion.estado_label}
                                            />
                                        </div>
                                        <p className="font-medium">
                                            {asignacion.asunto}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {asignacion.persona_nombre ??
                                                'Documento institucional'}{' '}
                                            ·{' '}
                                            {asignacion.plantilla ??
                                                'Borrador preparado'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Prioridad {asignacion.prioridad}
                                            {asignacion.fecha_esperada
                                                ? ` · fecha esperada ${formatDate(asignacion.fecha_esperada)}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Button
                                        render={
                                            <Link
                                                href={show(
                                                    asignacion.tramite_id,
                                                )}
                                            />
                                        }
                                    >
                                        Abrir expediente <ArrowRight />
                                    </Button>
                                </article>
                            ))
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00Z`));
}

ReviewerIndex.layout = { breadcrumbs: [{ title: 'Asignaciones', href: '#' }] };
