import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, MessageSquareText, MoveRight, PackageCheck } from 'lucide-react';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import TramiteEntregaController from '@/actions/App/Http/Controllers/TramiteEntregaController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import InputError from '@/components/input-error';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    tramite: {
        id: number;
        codigo: string;
        asunto: string;
        fecha_recepcion: string;
        estado: string;
        estado_label: string;
        actualizado_en: string | null;
    };
    comentario_publico: string | null;
    observaciones_visibles: Array<{
        categoria: string;
        titulo: string;
        descripcion: string;
        seccion: string | null;
        obligatoria: boolean;
    }>;
    documento_final: { numero: string } | null;
    documentos_recepcion: Array<{ id: number; nombre: string; categoria: string; version: number; vigente: boolean }>;
    entrega: {
        medio: string;
        receptor_nombre: string;
        receptor_documento: string | null;
        receptor_tipo: string;
        fecha_entrega: string;
        confirmado: boolean;
    } | null;
    puede_confirmar_entrega: boolean;
    informe_cierre: { numero_paginas: number; url_descarga: string } | null;
    historial: Array<{ estado: string; label: string; descripcion: string; fecha: string | null }>;
};

export default function EstudianteTramiteShow({ tramite, comentario_publico, observaciones_visibles, documento_final, documentos_recepcion, entrega, puede_confirmar_entrega, informe_cierre, historial }: Props) {
    return (
        <>
            <Head title={`Seguimiento · ${tramite.codigo}`} />
            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button render={<Link href={TramiteEstudianteController.index()} />} variant="outline" size="icon" aria-label="Volver a mis trámites"><ArrowLeft /></Button>
                    <div className="min-w-0 flex-1 space-y-2">
                        <p className="text-sm text-muted-foreground">Seguimiento de trámite</p>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">{tramite.codigo}</h1>
                            <TramiteStatusBadge estado={tramite.estado} label={tramite.estado_label} />
                        </div>
                        <p className="text-sm text-muted-foreground">{tramite.asunto}</p>
                    </div>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Estado del trámite</CardTitle>
                        <CardDescription>Recibido el {formatDate(tramite.fecha_recepcion)}.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-lg font-medium">{tramite.estado_label}</p>
                        {tramite.actualizado_en && <p className="text-sm text-muted-foreground">Última actualización: {formatDateTime(tramite.actualizado_en)}</p>}
                        {comentario_publico && (
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <h3 className="mb-2 flex items-center gap-2 text-sm font-medium"><MessageSquareText className="size-4" /> Mensaje sobre la decisión</h3>
                                <p className="whitespace-pre-wrap text-sm leading-6">{comentario_publico}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {documentos_recepcion.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Documentos recibidos</CardTitle>
                            <CardDescription>Archivos entregados físicamente y sus versiones conservadas.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-col gap-3">
                                {documentos_recepcion.map((documento) => (
                                    <li key={documento.id} className="flex flex-wrap items-center justify-between gap-3 border-b pb-3 last:border-0 last:pb-0">
                                        <span className="text-sm">{documento.nombre} · {documento.categoria.replaceAll('_', ' ')} · versión {documento.version}{documento.vigente ? '' : ' · reemplazado'}</span>
                                        <a href={TramiteController.download.url({ tramite: tramite.id, documento: documento.id })} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
                                            <Download data-icon="inline-start" /> Descargar
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {documento_final && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Documento oficial</CardTitle>
                            <CardDescription>Número {documento_final.numero}</CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {entrega && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Entrega del documento</CardTitle>
                            <CardDescription>{entrega.confirmado ? 'Recepción confirmada' : 'La recepción está pendiente de confirmación.'}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <Detail label="Medio" value={entrega.medio} />
                                <Detail label="Fecha de entrega" value={formatDateTime(entrega.fecha_entrega)} />
                                <Detail label="Receptor" value={`${entrega.receptor_nombre} · ${entrega.receptor_tipo}`} />
                                {entrega.receptor_documento && <Detail label="Documento de identidad" value={entrega.receptor_documento} />}
                            </dl>
                            {puede_confirmar_entrega && (
                                <Form {...TramiteEntregaController.confirm.form({ tramite: tramite.id })}>
                                    {({ errors, processing }) => (
                                        <div className="space-y-3 border-t pt-4">
                                            <div className="flex items-start gap-3">
                                                <Checkbox id="confirmar-recepcion" name="confirmar" value="1" required aria-invalid={Boolean(errors.confirmar)} />
                                                <Label htmlFor="confirmar-recepcion" className="items-start leading-5">Confirmo que recibí el documento oficial.</Label>
                                            </div>
                                            <InputError message={errors.confirmar} />
                                            <Input name="observacion" placeholder="Observación opcional" maxLength={1000} />
                                            <InputError message={errors.observacion} />
                                            <Button type="submit" disabled={processing}>
                                                {processing ? <Spinner data-icon="inline-start" /> : <PackageCheck data-icon="inline-start" />}
                                                Confirmar recepción
                                            </Button>
                                        </div>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {informe_cierre && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Informe de cierre</CardTitle>
                            <CardDescription>El expediente está cerrado. El informe contiene un resumen de la tramitación y la entrega.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">{informe_cierre.numero_paginas} páginas</p>
                            <a href={informe_cierre.url_descarga} className={buttonVariants({ variant: 'outline' })}>
                                <Download data-icon="inline-start" />
                                Descargar informe
                            </a>
                        </CardContent>
                    </Card>
                )}

                {observaciones_visibles.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Observaciones compartidas</CardTitle>
                            <CardDescription>El personal de gestión documentaria registra las correcciones del expediente.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {observaciones_visibles.map((observacion, index) => (
                                <article key={`${observacion.titulo}-${index}`} className="rounded-xl border p-4">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{observacion.categoria}{observacion.seccion ? ` · ${observacion.seccion}` : ''}</p>
                                    <h3 className="mt-1 font-medium">{observacion.titulo}</h3>
                                    <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">{observacion.descripcion}</p>
                                </article>
                            ))}
                            <p className="text-xs text-muted-foreground">Las subsanaciones se coordinan con Mesa de Partes.</p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Avance</CardTitle>
                        <CardDescription>Hitos principales del expediente.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {historial.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Aún no hay actualizaciones.</p>
                        ) : (
                            <ol className="space-y-4">
                                {historial.map((evento, index) => (
                                    <li key={`${evento.estado}-${index}`} className="flex gap-3 text-sm">
                                        <MoveRight className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <div>
                                            <p className="font-medium">{evento.label}</p>
                                            <p className="text-muted-foreground">{evento.descripcion}</p>
                                            {evento.fecha && <time className="text-xs text-muted-foreground">{formatDateTime(evento.fecha)}</time>}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${value.slice(0, 10)}T12:00:00Z`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

EstudianteTramiteShow.layout = { breadcrumbs: [{ title: 'Mis trámites', href: TramiteEstudianteController.index() }, { title: 'Seguimiento', href: '#' }] };
