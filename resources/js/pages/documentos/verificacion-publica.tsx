import { Head } from '@inertiajs/react';
import HelpTools from '@/components/help-tools';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type DocumentoVerificado = {
    tipo: string;
    numero: string;
    expediente: string | null;
    fecha_emision: string | null;
    codigo: string;
};

type ResultadoVerificacion = {
    estado: 'vigente' | 'anulado' | 'sustituido' | 'invalido';
    documento: DocumentoVerificado | null;
};

export default function VerificacionPublica({
    codigo,
    resultado,
    institucion,
    action,
}: {
    codigo: string;
    resultado: ResultadoVerificacion | null;
    institucion: string;
    action: string;
}) {
    return (
        <>
            <Head title="Verificar documento">
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <main className="mx-auto flex min-h-screen w-full max-w-2xl items-center px-4 py-12">
                <Card className="w-full">
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Verificación de documento
                        </CardTitle>
                        <CardDescription>
                            Ingresa el código impreso en el documento para
                            consultar su vigencia ante {institucion}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <form
                            method="get"
                            action={action}
                            className="flex flex-col gap-3"
                        >
                            <label
                                htmlFor="codigo"
                                className="text-sm font-medium"
                            >
                                Código de verificación
                            </label>
                            <Input
                                id="codigo"
                                name="codigo"
                                maxLength={19}
                                autoComplete="off"
                                placeholder="XXXX-XXXX-XXXX-XXXX"
                                defaultValue={codigo}
                                className="font-mono uppercase"
                            />
                            <Button type="submit" className="self-start">
                                Verificar
                            </Button>
                        </form>

                        {resultado?.estado === 'vigente' &&
                            resultado.documento && (
                                <section
                                    aria-live="polite"
                                    className="rounded-xl border border-primary/30 bg-primary/5 p-4"
                                >
                                    <h2 className="mb-3 font-semibold text-primary">
                                        Documento válido y vigente
                                    </h2>
                                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                        <Detalle
                                            etiqueta="Tipo de documento"
                                            valor={resultado.documento.tipo}
                                        />
                                        <Detalle
                                            etiqueta="Número"
                                            valor={resultado.documento.numero}
                                        />
                                        <Detalle
                                            etiqueta="Expediente"
                                            valor={
                                                resultado.documento
                                                    .expediente ??
                                                'No disponible'
                                            }
                                        />
                                        <Detalle
                                            etiqueta="Fecha de emisión"
                                            valor={
                                                resultado.documento
                                                    .fecha_emision ??
                                                'No disponible'
                                            }
                                        />
                                        <Detalle
                                            etiqueta="Código"
                                            valor={resultado.documento.codigo}
                                        />
                                    </dl>
                                </section>
                            )}

                        {resultado?.estado === 'anulado' && (
                            <p
                                role="status"
                                className="rounded-xl border border-border bg-muted p-4 text-sm"
                            >
                                Este documento fue anulado y ya no es válido.
                            </p>
                        )}
                        {resultado?.estado === 'sustituido' && (
                            <p
                                role="status"
                                className="rounded-xl border border-border bg-muted p-4 text-sm"
                            >
                                Este documento fue sustituido por una versión
                                posterior y ya no es válido.
                            </p>
                        )}
                        {resultado?.estado === 'invalido' && (
                            <p
                                role="alert"
                                className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                            >
                                Documento no encontrado o código no válido.
                                Verifica el código e inténtalo nuevamente.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </main>
            <HelpTools />
        </>
    );
}

function Detalle({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium break-words">{valor}</dd>
        </div>
    );
}
