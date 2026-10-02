import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileCheck2, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteDocumentoFinalController from '@/actions/App/Http/Controllers/TramiteDocumentoFinalController';

type Props = {
    modelo_oficial_pendiente: boolean;
    tramite: {
        id: number;
        codigo: string;
        asunto: string;
        estado: 'aprobado' | 'rechazado';
    };
    revision: {
        decision: 'aprobado' | 'rechazado';
        conclusion: string | null;
        comentario_publico: string | null;
        numero_ronda: number;
    };
    borrador: {
        version: number;
        plantilla: string;
        asunto: string;
        fecha_documento: string;
        firmante: string | null;
        firma_perfil_registrada: boolean;
    };
};

export default function DocumentoFinal({
    tramite,
    revision,
    borrador,
    modelo_oficial_pendiente,
}: Props) {
    return (
        <>
            <Head title={`Emitir documento · ${tramite.codigo}`} />

            <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <Link
                        href={TramiteController.show({ tramite: tramite.id })}
                        className={buttonVariants({
                            variant: 'outline',
                            size: 'icon',
                        })}
                        aria-label="Volver al trámite"
                    >
                        <ArrowLeft />
                    </Link>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Emisión de documento oficial
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {tramite.codigo}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tramite.asunto}
                        </p>
                    </div>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Versión aprobada para emisión</CardTitle>
                        <CardDescription>
                            La numeración se reserva al confirmar. El PDF se
                            genera desde la versión exacta que revisó el
                            docente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        {modelo_oficial_pendiente && (
                            <p className="text-sm text-destructive sm:col-span-2">
                                Falta el modelo institucional aprobado para este
                                tipo de trámite. Puedes registrar y revisar el
                                expediente, pero aún no emitir su PDF oficial.
                            </p>
                        )}
                        <Detail
                            label="Resultado"
                            value={
                                revision.decision === 'aprobado'
                                    ? 'Aprobado'
                                    : 'Rechazado'
                            }
                        />
                        <Detail
                            label="Ronda de revisión"
                            value={revision.numero_ronda.toString()}
                        />
                        <Detail label="Plantilla" value={borrador.plantilla} />
                        <Detail
                            label="Versión revisada"
                            value={borrador.version.toString()}
                        />
                        <Detail
                            label="Asunto del documento"
                            value={borrador.asunto}
                        />
                        <Detail
                            label="Fecha del documento"
                            value={formatDate(borrador.fecha_documento)}
                        />
                        <Detail
                            label="Firmante"
                            value={borrador.firmante ?? 'Cuenta eliminada'}
                        />
                        <Detail
                            label="Firma del perfil"
                            value={
                                borrador.firma_perfil_registrada
                                    ? 'Registrada; se insertará en el PDF'
                                    : 'Falta registrar la firma en Mi perfil'
                            }
                        />
                        {revision.conclusion && (
                            <Detail
                                label="Conclusión"
                                value={revision.conclusion}
                            />
                        )}
                        {revision.comentario_publico && (
                            <Detail
                                label="Comunicación al interesado"
                                value={revision.comentario_publico}
                            />
                        )}
                    </CardContent>
                    <Form
                        {...TramiteDocumentoFinalController.emit.form({
                            tramite: tramite.id,
                        })}
                    >
                        {({ errors, processing }) => (
                            <>
                                <CardFooter className="flex flex-col items-stretch gap-4 border-t pt-5">
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="confirmar-emision"
                                            name="confirmar"
                                            value="1"
                                            required
                                            aria-invalid={Boolean(
                                                errors.confirmar,
                                            )}
                                        />
                                        <Label
                                            htmlFor="confirmar-emision"
                                            className="items-start leading-5"
                                        >
                                            Confirmo la emisión oficial y
                                            entiendo que el correlativo no se
                                            reutiliza, aunque falle la
                                            generación del PDF.
                                        </Label>
                                    </div>
                                    <InputError message={errors.confirmar} />
                                    <InputError message={errors.documento} />
                                    {!borrador.firma_perfil_registrada && (
                                        <p className="text-sm text-destructive">
                                            El firmante debe guardar su firma
                                            escaneada en Mi perfil antes de
                                            emitir el documento.
                                        </p>
                                    )}
                                    <div className="flex flex-col-reverse justify-between gap-3 sm:flex-row sm:items-center">
                                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <ShieldCheck />
                                            El archivo se guarda de forma
                                            privada y se verificará con SHA-256
                                            en cada descarga.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !borrador.firma_perfil_registrada ||
                                                modelo_oficial_pendiente
                                            }
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <FileCheck2 data-icon="inline-start" />
                                            )}
                                            Emitir documento oficial
                                        </Button>
                                    </div>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
            </main>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm leading-6">{value}</p>
        </div>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'long' }).format(
        new Date(`${value}T12:00:00`),
    );
}

DocumentoFinal.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Emisión oficial', href: '#' },
    ],
};
