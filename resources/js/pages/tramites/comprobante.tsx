import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import SupportWidget from '@/components/support-widget';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Comprobante = {
    tramite_id: number;
    codigo: string;
    fecha_recepcion: string;
    fecha_registro: string | null;
    tipo_tramite: string;
    estado_inicial: string;
    destino: string;
    interesado: string;
    asunto: string;
};

export default function TramiteComprobante({
    institucion,
    comprobante,
}: {
    institucion: string;
    comprobante: Comprobante;
}) {
    return (
        <>
            <Head title={`Comprobante · ${comprobante.codigo}`} />
            <main className="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-4 p-4 py-8 print:min-h-0 print:max-w-none print:p-0">
                <Card className="print:rounded-none print:border-foreground print:shadow-none">
                    <CardHeader className="border-b text-center">
                        <CardTitle className="text-xl">{institucion}</CardTitle>
                        <p>Comprobante de recepción de trámite</p>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <dl className="grid gap-4 sm:grid-cols-[13rem_1fr]">
                            <dt className="font-medium">
                                Código de referencia
                            </dt>
                            <dd className="text-lg font-semibold">
                                {comprobante.codigo}
                            </dd>
                            <dt className="font-medium">Fecha de recepción</dt>
                            <dd>{formatDate(comprobante.fecha_recepcion)}</dd>
                            {comprobante.fecha_registro && (
                                <>
                                    <dt className="font-medium">
                                        Fecha y hora de registro
                                    </dt>
                                    <dd>
                                        {formatDateTime(
                                            comprobante.fecha_registro,
                                        )}
                                    </dd>
                                </>
                            )}
                            <dt className="font-medium">Tipo de trámite</dt>
                            <dd>{comprobante.tipo_tramite}</dd>
                            <dt className="font-medium">Estado inicial</dt>
                            <dd>{comprobante.estado_inicial}</dd>
                            <dt className="font-medium">Destino</dt>
                            <dd>{comprobante.destino}</dd>
                            <dt className="font-medium">Interesado</dt>
                            <dd>{comprobante.interesado}</dd>
                            <dt className="font-medium">Asunto</dt>
                            <dd>{comprobante.asunto}</dd>
                        </dl>
                        <p className="rounded-xl border bg-muted p-4 font-medium print:bg-transparent">
                            Conserve este código como referencia de su trámite.
                        </p>
                    </CardContent>
                </Card>
                <div className="flex justify-center gap-3 print:hidden">
                    <Button type="button" onClick={() => window.print()}>
                        <Printer data-icon="inline-start" /> Imprimir
                        comprobante
                    </Button>
                    <Button
                        variant="outline"
                        render={
                            <Link
                                href={TramiteController.show(
                                    comprobante.tramite_id,
                                )}
                            />
                        }
                    >
                        <ArrowLeft data-icon="inline-start" /> Abrir expediente
                    </Button>
                </div>
            </main>
            <div className="print:hidden">
                <SupportWidget />
            </div>
        </>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${value}T12:00:00Z`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
