import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ClipboardList } from 'lucide-react';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Tramite = {
    id: number;
    codigo: string;
    asunto: string;
    fecha_recepcion: string;
    estado: string;
    estado_label: string;
    actualizado_en: string | null;
};

export default function EstudianteTramitesIndex({
    tramites,
}: {
    tramites: { data: Tramite[] };
}) {
    return (
        <>
            <Head title="Mis trámites" />
            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Mis trámites
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta el estado de los expedientes vinculados a tu
                        cuenta.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardList className="size-5" /> Seguimiento
                        </CardTitle>
                        <CardDescription>
                            Solo se muestran trámites asociados a tu cuenta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {tramites.data.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                Aún no hay trámites asociados a tu cuenta. Para
                                consultas, contacta a Mesa de Partes.
                            </div>
                        ) : (
                            tramites.data.map((tramite) => (
                                <article
                                    key={tramite.id}
                                    className="grid gap-4 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">
                                                {tramite.codigo}
                                            </span>
                                            <TramiteStatusBadge
                                                estado={tramite.estado}
                                                label={tramite.estado_label}
                                            />
                                        </div>
                                        <p className="font-medium">
                                            {tramite.asunto}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Recibido{' '}
                                            {formatDate(
                                                tramite.fecha_recepcion,
                                            )}
                                        </p>
                                    </div>
                                    <Button
                                        render={
                                            <Link
                                                href={TramiteEstudianteController.show(
                                                    { tramite: tramite.id },
                                                )}
                                            />
                                        }
                                        variant="outline"
                                    >
                                        Ver seguimiento <ArrowRight />
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

EstudianteTramitesIndex.layout = {
    breadcrumbs: [{ title: 'Mis trámites', href: '#' }],
};
