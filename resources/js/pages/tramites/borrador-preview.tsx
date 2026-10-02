import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import { buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    tramite: { id: number; codigo: string };
    borrador: {
        id: number;
        version: number;
        estado: string;
        actual: boolean;
        plantilla: string;
        version_plantilla: number;
        creador: string | null;
        created_at: string | null;
        contenido: string | null;
        puede_pdf: boolean;
    };
};

export default function BorradorPreview({ tramite, borrador }: Props) {
    return (
        <>
            <Head title={`Borrador v${borrador.version} · ${tramite.codigo}`} />

            <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-5 p-4 md:p-6">
                <Link
                    className={buttonVariants({ variant: 'outline' })}
                    href={TramiteController.show({ tramite: tramite.id })}
                >
                    <ArrowLeft data-icon="inline-start" />
                    Volver al expediente
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {tramite.codigo} · Borrador v{borrador.version}
                            {borrador.actual ? ' · actual' : ''}
                        </CardTitle>
                        <CardDescription>
                            {borrador.plantilla} · plantilla v
                            {borrador.version_plantilla} ·{' '}
                            {borrador.estado.replaceAll('_', ' ')}
                            {borrador.creador ? ` · ${borrador.creador}` : ''}
                        </CardDescription>
                        {borrador.created_at && (
                            <time
                                dateTime={borrador.created_at}
                                className="text-sm text-muted-foreground"
                            >
                                Creado el{' '}
                                {new Date(borrador.created_at).toLocaleString(
                                    'es-PE',
                                    { timeZone: 'America/Lima' },
                                )}
                            </time>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="font-medium">
                            BORRADOR SIN NUMERACIÓN OFICIAL
                        </p>
                        {borrador.puede_pdf ? (
                            <iframe
                                title="Vista previa PDF del borrador guardado"
                                src={`/tramites/${tramite.id}/borradores/${borrador.id}/pdf`}
                                sandbox="allow-same-origin"
                                className="h-[720px] w-full rounded-md border bg-muted"
                            />
                        ) : borrador.contenido ? (
                            <p className="break-words whitespace-pre-wrap">
                                {borrador.contenido}
                            </p>
                        ) : (
                            <p>
                                Esta versión histórica no tiene texto de vista
                                previa guardado.
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {borrador.puede_pdf
                                ? 'Vista previa PDF provisional. No constituye firma, aprobación ni numeración oficial.'
                                : 'Vista previa provisional. No constituye PDF, firma, aprobación ni numeración oficial.'}
                        </p>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
