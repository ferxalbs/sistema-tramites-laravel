import { Head, setLayoutProps } from '@inertiajs/react';
import { Download } from 'lucide-react';
import TramiteBorradorController from '@/actions/App/Http/Controllers/TramiteBorradorController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
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
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Expediente',
                href: TramiteController.show({ tramite: tramite.id }),
            },
            {
                title: `Vista previa (v${borrador.version})`,
                href: TramiteBorradorController.show.url({
                    tramite: tramite.id,
                    borrador: borrador.id,
                }),
            },
        ],
    });

    return (
        <>
            <Head title={`Borrador v${borrador.version} · ${tramite.codigo}`} />

            <main className="flex flex-1 flex-col p-4 md:p-6">
                <Card className="mx-auto w-full max-w-5xl">
                    <CardHeader>
                        <CardTitle>
                            <h1 className="flex flex-wrap items-center gap-2">
                                {tramite.codigo} · Borrador v{borrador.version}
                                {borrador.actual && (
                                    <Badge variant="secondary">Actual</Badge>
                                )}
                            </h1>
                        </CardTitle>
                        <CardDescription>
                            {borrador.plantilla} (v{borrador.version_plantilla})
                            · {borrador.estado.replaceAll('_', ' ')}
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
                    <CardContent>
                        {borrador.puede_pdf ? (
                            <iframe
                                title="Vista previa PDF del borrador guardado"
                                src={TramiteBorradorController.pdf.url({
                                    tramite: tramite.id,
                                    borrador: borrador.id,
                                })}
                                className="h-[72vh] min-h-96 w-full"
                            />
                        ) : (
                            <p className="break-words whitespace-pre-wrap">
                                {borrador.contenido ||
                                    'Esta versión histórica no tiene texto de vista previa guardado.'}
                            </p>
                        )}
                    </CardContent>
                    <CardFooter className="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {borrador.puede_pdf
                                ? 'PDF provisional; puede incluir la firma del perfil. No constituye aprobación ni numeración oficial.'
                                : 'Vista previa provisional. No constituye PDF, firma, aprobación ni numeración oficial.'}
                        </p>
                        {borrador.puede_pdf && (
                            <Button
                                variant="outline"
                                render={
                                    <a
                                        href={TramiteBorradorController.pdf.url(
                                            {
                                                tramite: tramite.id,
                                                borrador: borrador.id,
                                            },
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    />
                                }
                            >
                                <Download data-icon="inline-start" /> Abrir PDF
                                provisional
                            </Button>
                        )}
                    </CardFooter>
                </Card>
            </main>
        </>
    );
}
