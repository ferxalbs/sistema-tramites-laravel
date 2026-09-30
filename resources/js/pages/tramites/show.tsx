import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Download, FileCheck2, FilePlus2, FileText, Pencil, Printer, Send } from 'lucide-react';
import TramiteBorradorController from '@/actions/App/Http/Controllers/TramiteBorradorController';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEntregaController from '@/actions/App/Http/Controllers/TramiteEntregaController';
import TramiteDocumentoFinalController from '@/actions/App/Http/Controllers/TramiteDocumentoFinalController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import InputError from '@/components/input-error';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type TramiteDocument = {
    id: number;
    categoria: string;
    nombre_original: string;
    mime_type: string;
    tamano_bytes: number;
    version: number;
    vigente: boolean;
    documento_anterior_id: number | null;
    created_at: string | null;
};

type TramiteEvent = {
    id: number;
    accion: string;
    descripcion: string;
    estado_anterior: string | null;
    estado_nuevo: string | null;
    usuario: string | null;
    created_at: string | null;
};

type TramiteDraftVersion = {
    id: number;
    version: number;
    estado: string;
    plantilla: string;
    actual: boolean;
    created_at: string | null;
};

type TramiteDetail = {
    id: number;
    codigo: string;
    clasificacion: string;
    programa: string | null;
    tipo_documento: string;
    formato_salida: string | null;
    modalidad_documento: string | null;
    persona_nombre: string | null;
    persona_identificador: string | null;
    propietario: string | null;
    destino_tipo: string;
    destino_nombre: string;
    asunto: string;
    descripcion: string | null;
    prioridad: string;
    fecha_recepcion: string;
    fecha_llegada_oficina: string | null;
    fecha_presentacion_original: string | null;
    numero_expediente_externo: string | null;
    area_procedencia: string | null;
    persona_entrega_documento: string | null;
    observacion_recepcion: string | null;
    folios: number | null;
    personas_relacionadas: Array<{ id: number; nombres: string; apellidos: string | null; dni: string | null; cargo_funcion: string | null; tipo_relacion: string }>;
    destinatarios: Array<{ id: number; nombres: string; apellidos: string | null; cargo_institucional_id: number | null; cargo_catalogo: string | null; cargo_texto: string | null; correo_institucional: string | null }>;
    personas_mencionadas: Array<{ id: number; nombres: string; apellidos: string | null; dni: string | null; cargo_funcion: string | null; descripcion: string | null }>;
    estado: string;
    estado_label: string;
    recibido_por: string | null;
    puede_gestionar_asignacion: boolean;
    puede_corregir_revision: boolean;
    puede_gestionar_documentos_recepcion: boolean;
    puede_registrar_subsanacion: boolean;
    puede_editar_recepcion: boolean;
    puede_emitir_documento_final: boolean;
    puede_anular_documento_final: boolean;
    url_gestion_entrega: string | null;
    documento_final: {
        id: number;
        numero: string;
        tipo: string;
        estado: string;
        version: number;
        sha256: string | null;
        tamano_bytes: number | null;
        numero_paginas: number | null;
        fecha_emision: string | null;
        url_descarga: string | null;
    } | null;
    documentos_finales_anteriores: Array<{
        id: number;
        version: number;
        numero: string;
        estado: string;
        documento_anterior_id: number | null;
        fecha_anulacion: string | null;
        url_descarga: string | null;
    }>;
    asignacion_actual: {
        id: number;
        destino: string;
        revisor_id: number;
        revisor: string;
        motivo: string;
        instrucciones_revision: string | null;
        fecha_esperada: string | null;
        fecha_inicio_revision: string | null;
    } | null;
    asignaciones: Array<{
        id: number;
        destino: string;
        revisor: string | null;
        asignado_por: string | null;
        motivo: string;
        estado: string;
        activa: boolean;
        motivo_finalizacion: string | null;
        fecha_esperada: string | null;
        created_at: string | null;
        fecha_finalizacion: string | null;
    }>;
    borrador_actual: {
        id: number;
        version: number;
        estado: string;
        plantilla: string;
        preparado_en: string | null;
    } | null;
    borradores: TramiteDraftVersion[];
    revision_rondas: Array<{
        numero: number;
        estado: string;
        revisor: string | null;
        version: number | null;
        resumen_observacion: string | null;
        resumen_correccion: string | null;
        conclusion: string | null;
        comentario_publico: string | null;
        comentario_interno: string | null;
        observaciones: Array<{
            id: number;
            categoria: string;
            titulo: string;
            descripcion: string;
            seccion: string | null;
            obligatoria: boolean;
            visible_para_interesado: boolean;
            respuesta: string | null;
        }>;
    }>;
    documentos: TramiteDocument[];
    eventos: TramiteEvent[];
};

export default function TramiteShow({ tramite }: { tramite: TramiteDetail }) {
    return (
        <>
            <Head title={tramite.codigo} />

            <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
                        <Button render={<Link href={TramiteController.index()} />} variant="outline" size="icon" aria-label="Volver a la bandeja">
                            <ArrowLeft />
                        </Button>
                        <div className="space-y-1">
                            <p className="text-sm text-muted-foreground">Código interno del trámite</p>
                            <h1 className="text-2xl font-semibold tracking-tight">{tramite.codigo}</h1>
                            <p className="text-sm text-muted-foreground"><span className="font-medium">Sumilla:</span> {tramite.asunto}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {tramite.puede_editar_recepcion && (
                            <Button variant="outline" render={<Link href={TramiteController.edit({ tramite: tramite.id })} />}>
                                <Pencil data-icon="inline-start" /> Editar recepción
                            </Button>
                        )}
                        <Button variant="outline" render={<Link href={TramiteController.receipt({ tramite: tramite.id })} />}>
                            <Printer data-icon="inline-start" />
                            Comprobante
                        </Button>
                        {['digitalizado', 'borrador_preparado'].includes(tramite.estado) && (
                            <Button render={<Link href={TramiteBorradorController.create({ tramite: tramite.id })} />}>
                                <FilePlus2 />
                                {tramite.borrador_actual ? 'Nueva versión' : 'Preparar borrador'}
                            </Button>
                        )}
                        {tramite.puede_gestionar_asignacion && tramite.estado === 'pendiente_asignacion' && (
                            <Button render={<Link href={TramiteAsignacionController.create({ tramite: tramite.id })} />}>
                                <ClipboardCheck />
                                Asignar revisor
                            </Button>
                        )}
                        {tramite.puede_emitir_documento_final && (
                            <Link
                                href={TramiteDocumentoFinalController.preview({ tramite: tramite.id })}
                                className={buttonVariants()}
                            >
                                <FileCheck2 data-icon="inline-start" />
                                Emitir documento oficial
                            </Link>
                        )}
                        {tramite.url_gestion_entrega && (
                            <Link href={TramiteEntregaController.show({ tramite: tramite.id })} className={buttonVariants({ variant: 'outline' })}>
                                <ClipboardCheck data-icon="inline-start" />
                                Gestionar entrega
                            </Link>
                        )}
                        <TramiteStatusBadge estado={tramite.estado} label={tramite.estado_label} />
                    </div>
                </header>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,1fr)]">
                    <div className="space-y-5">
                        {tramite.documento_final && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Documento oficial</CardTitle>
                                    <CardDescription>Versión {tramite.documento_final.version} · {tramite.documento_final.tipo}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Detail label="Número oficial" value={tramite.documento_final.numero} />
                                    <Detail label="Estado del archivo" value={tramite.documento_final.estado === 'emitido' ? 'Emitido y verificado' : 'Generación en curso'} />
                                    <Detail label="SHA-256" value={tramite.documento_final.sha256 ?? 'Pendiente de verificación'} />
                                    <Detail label="Páginas" value={tramite.documento_final.numero_paginas?.toString() ?? 'Pendiente'} />
                                    {tramite.documento_final.fecha_emision && (
                                        <Detail label="Fecha de emisión" value={formatDateTime(tramite.documento_final.fecha_emision)} />
                                    )}
                                </CardContent>
                                {tramite.documento_final.url_descarga && (
                                    <CardFooter className="justify-end border-t pt-4">
                                        <a
                                            className={buttonVariants({ variant: 'outline' })}
                                            href={tramite.documento_final.url_descarga}
                                        >
                                            <Download data-icon="inline-start" />
                                            Descargar PDF oficial
                                        </a>
                                    </CardFooter>
                                )}
                                {tramite.puede_anular_documento_final && (
                                    <Form {...TramiteDocumentoFinalController.annul.form({ tramite: tramite.id, documento: tramite.documento_final.id })}>
                                        {({ errors, processing }) => (
                                            <CardFooter className="flex flex-col items-stretch gap-3 border-t pt-4">
                                                <p className="text-sm text-muted-foreground">Anular o autorizar una sustitución conserva el PDF y consume definitivamente este número.</p>
                                                <Label htmlFor="motivo-documento-final">Motivo administrativo</Label>
                                                <Input id="motivo-documento-final" name="motivo" required minLength={10} maxLength={1000} aria-invalid={Boolean(errors.motivo)} />
                                                <InputError message={errors.motivo} />
                                                <InputError message={errors.accion} />
                                                <div className="flex flex-wrap gap-2">
                                                    <Button type="submit" name="accion" value="anular" variant="destructive" disabled={processing}>Anular documento</Button>
                                                    <Button type="submit" name="accion" value="sustituir" variant="outline" disabled={processing}>Autorizar sustitución</Button>
                                                </div>
                                            </CardFooter>
                                        )}
                                    </Form>
                                )}
                            </Card>
                        )}

                        {tramite.documentos_finales_anteriores.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Versiones oficiales anteriores</CardTitle>
                                    <CardDescription>Los números y archivos históricos se conservan en el historial del expediente.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {tramite.documentos_finales_anteriores.map((documento) => (
                                        <div key={documento.id} className="space-y-2">
                                            <p className="font-medium">Versión {documento.version} · {documento.numero}</p>
                                            <p className="text-sm text-muted-foreground">{documento.estado === 'sustituido' ? 'Sustituido' : documento.estado === 'anulado' ? 'Anulado' : 'Generación fallida'}{documento.fecha_anulacion ? ` · ${formatDateTime(documento.fecha_anulacion)}` : ''}</p>
                                            {documento.url_descarga && (
                                                <a href={documento.url_descarga} className={buttonVariants({ variant: 'outline' })}>
                                                    <Download data-icon="inline-start" /> Descargar PDF histórico
                                                </a>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {tramite.asignaciones.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Asignación de revisión</CardTitle>
                                    <CardDescription>El trámite conserva cada asignación y su motivo en el historial.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {tramite.asignacion_actual && (
                                        <div className="rounded-xl border p-4">
                                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                                <div className="space-y-1 text-sm">
                                                    <p className="font-medium">{tramite.asignacion_actual.destino === 'docente' ? 'Docente' : 'Oficina'} · {tramite.asignacion_actual.revisor}</p>
                                                    <p className="text-muted-foreground">Motivo: {tramite.asignacion_actual.motivo}</p>
                                                    {tramite.asignacion_actual.instrucciones_revision && <p className="text-muted-foreground">Instrucciones: {tramite.asignacion_actual.instrucciones_revision}</p>}
                                                    <p className="text-muted-foreground">Fecha esperada: {tramite.asignacion_actual.fecha_esperada ? formatDate(tramite.asignacion_actual.fecha_esperada) : 'No definida'}</p>
                                                </div>
                                                {tramite.puede_gestionar_asignacion && !tramite.asignacion_actual.fecha_inicio_revision && (
                                                    <Button render={<Link href={TramiteAsignacionController.reassign({ tramite: tramite.id })} />} variant="outline">
                                                        Reasignar
                                                    </Button>
                                                )}
                                            </div>
                                            {tramite.puede_gestionar_asignacion && !tramite.asignacion_actual.fecha_inicio_revision && (
                                                <Form
                                                    action={TramiteAsignacionController.cancel.url({ tramite: tramite.id })}
                                                    method="post"
                                                    options={{ preserveScroll: true }}
                                                    className="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-[1fr_auto] sm:items-end"
                                                >
                                                    {({ errors, processing }) => (
                                                        <>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="motivo_finalizacion">Motivo para cancelar</Label>
                                                                <Textarea
                                                                    id="motivo_finalizacion"
                                                                    name="motivo_finalizacion"
                                                                    required
                                                                    minLength={8}
                                                                    maxLength={1000}
                                                                    rows={2}
                                                                />
                                                                <InputError message={errors.motivo_finalizacion} />
                                                            </div>
                                                            <Button type="submit" variant="outline" disabled={processing}>Cancelar asignación</Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    )}
                                    <ol className="space-y-3">
                                        {tramite.asignaciones.map((asignacion) => (
                                            <li key={asignacion.id} className="flex flex-col justify-between gap-1 border-b pb-3 text-sm last:border-0 last:pb-0 sm:flex-row sm:items-center">
                                                <div>
                                                    <p className="font-medium">{asignacion.destino === 'docente' ? 'Docente' : 'Oficina'} · {asignacion.revisor ?? 'Cuenta eliminada'} · {asignacion.estado}</p>
                                                    <p className="text-muted-foreground">Motivo: {asignacion.motivo_finalizacion ?? asignacion.motivo}</p>
                                                    <p className="text-xs text-muted-foreground">Asignado por {asignacion.asignado_por ?? 'Cuenta eliminada'}</p>
                                                </div>
                                                {asignacion.created_at && <time className="text-xs text-muted-foreground">{formatDateTime(asignacion.created_at)}</time>}
                                            </li>
                                        ))}
                                    </ol>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Información de recepción</CardTitle>
                                <CardDescription>Datos registrados por la mesa de partes.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        {tramite.persona_nombre !== null && <Detail label="Persona solicitante" value={tramite.persona_nombre} />}
                        <Detail label="DNI / documento de identidad" value={tramite.persona_identificador ?? 'No registrado'} />
                        <Detail label="Cuenta asociada" value={tramite.propietario ?? 'Sin cuenta vinculada'} />
                                <Detail label="Clasificación" value={tramite.clasificacion} />
                                <Detail label="Programa" value={tramite.programa ?? 'No registrado'} />
                                <Detail label="Tipo de trámite" value={tramite.tipo_documento} />
                                <Detail label="Formato previsto" value={tramite.formato_salida ?? 'Pendiente'} />
                                {tramite.modalidad_documento && <Detail label="Modalidad" value={tramite.modalidad_documento} />}
                                <Detail label="Destino" value={`${tramite.destino_tipo}: ${tramite.destino_nombre}`} />
                                <Detail label="Fecha y hora de recepción en Mesa de Partes" value={tramite.fecha_llegada_oficina ?? 'No registrada'} />
                                <Detail label="Fecha del documento (FUT)" value={tramite.fecha_presentacion_original ? formatDate(tramite.fecha_presentacion_original) : 'No registrada'} />
                                <Detail label="Referencia física externa" value={tramite.numero_expediente_externo ?? 'No registrada'} />
                                <Detail label="Área de procedencia" value={tramite.area_procedencia ?? 'No registrada'} />
                                <Detail label="Persona que entregó" value={tramite.persona_entrega_documento ?? 'No registrada'} />
                                <Detail label="Prioridad" value={tramite.prioridad} />
                                <Detail label="Cantidad de folios" value={tramite.folios?.toString() ?? 'No registrado'} />
                                <Detail label="Recibido por" value={tramite.recibido_por ?? 'Usuario desactivado'} />
                                <div className="sm:col-span-2">
                                    <Detail label="Resumen de la solicitud (sumilla)" value={tramite.asunto} />
                                    <Detail label="Fundamentación del pedido / detalle" value={tramite.descripcion || 'Sin descripción'} />
                                    <Detail label="Observación de recepción" value={tramite.observacion_recepcion ?? 'Sin observaciones'} />
                                </div>
                            </CardContent>
                        </Card>

                        {(tramite.personas_relacionadas.length > 0 || tramite.destinatarios.length > 0 || tramite.personas_mencionadas.length > 0) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Listas preliminares</CardTitle>
                                    <CardDescription>Datos internos registrados en la recepción física.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5">
                                    {tramite.personas_relacionadas.length > 0 && <div className="grid gap-2">
                                        <h3 className="font-medium">Personas relacionadas</h3>
                                        {tramite.personas_relacionadas.map((persona) => (
                                            <p key={persona.id} className="text-sm">{persona.nombres} {persona.apellidos} · {persona.tipo_relacion}{persona.dni ? ` · DNI ${persona.dni}` : ''}{persona.cargo_funcion ? ` · ${persona.cargo_funcion}` : ''}</p>
                                        ))}
                                    </div>}
                                    {tramite.destinatarios.length > 0 && <div className="grid gap-2">
                                        <h3 className="font-medium">Destinatarios preliminares</h3>
                                        {tramite.destinatarios.map((persona, index) => (
                                            <p key={persona.id} className="text-sm">{index === 0 ? 'Principal · ' : ''}{persona.nombres} {persona.apellidos}{persona.cargo_catalogo ? ` · ${persona.cargo_catalogo}` : ''}{persona.cargo_texto ? ` · ${persona.cargo_texto}` : ''}{persona.correo_institucional ? ` · ${persona.correo_institucional}` : ''}</p>
                                        ))}
                                    </div>}
                                    {tramite.personas_mencionadas.length > 0 && <div className="grid gap-2">
                                        <h3 className="font-medium">Personas mencionadas</h3>
                                        {tramite.personas_mencionadas.map((persona) => (
                                            <p key={persona.id} className="text-sm">{persona.nombres} {persona.apellidos}{persona.dni ? ` · DNI ${persona.dni}` : ''}{persona.cargo_funcion ? ` · ${persona.cargo_funcion}` : ''}{persona.descripcion ? ` · ${persona.descripcion}` : ''}</p>
                                        ))}
                                    </div>}
                                </CardContent>
                            </Card>
                        )}

                        {tramite.borradores.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Borradores y versiones</CardTitle>
                                    <CardDescription>Historial de documentos de trabajo guardados para este trámite.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <ol className="space-y-3">
                                        {tramite.borradores.map((borrador) => (
                                            <li key={borrador.id} className="flex flex-col justify-between gap-1 border-b pb-3 text-sm last:border-0 last:pb-0 sm:flex-row sm:items-center">
                                                <div>
                                                    <p className="font-medium">Versión {borrador.version}{borrador.actual ? ' · actual' : ''}</p>
                                                    <p className="text-muted-foreground">{borrador.plantilla} · {borrador.estado.replaceAll('_', ' ')}</p>
                                                </div>
                                                <time className="text-xs text-muted-foreground">
                                                    {borrador.created_at ? formatDateTime(borrador.created_at) : ''}
                                                </time>
                                                <Link className={buttonVariants({ variant: 'outline', size: 'sm' })} href={TramiteBorradorController.show({ tramite: tramite.id, borrador: borrador.id })}>
                                                    Vista previa
                                                </Link>
                                            </li>
                                        ))}
                                    </ol>
                                    {tramite.estado === 'borrador_preparado' && tramite.borrador_actual?.estado === 'preparado_asignacion' && (
                                        <Form
                                            action={TramiteBorradorController.prepareAssignment.url({ tramite: tramite.id })}
                                            method="post"
                                            className="flex justify-end"
                                        >
                                            <Button type="submit">
                                                <Send />
                                                Enviar a asignación
                                            </Button>
                                        </Form>
                                    )}
                                    {tramite.puede_corregir_revision && (
                                        <div className="flex justify-end">
                                            <Button render={<Link href={TramiteBorradorController.correction({ tramite: tramite.id })} />}>
                                                <FilePlus2 />
                                                Corregir y reenviar
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {tramite.revision_rondas.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Revisión y decisiones</CardTitle>
                                    <CardDescription>Historial de rondas, observaciones y resultados asociados a las versiones del borrador.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {tramite.revision_rondas.map((ronda) => (
                                        <section key={ronda.numero} className="space-y-3 rounded-xl border p-4">
                                            <div className="flex flex-wrap justify-between gap-2 text-sm">
                                                <p className="font-semibold">Ronda {ronda.numero} · {ronda.estado.replaceAll('_', ' ')}</p>
                                                <p className="text-muted-foreground">{ronda.revisor ?? 'Cuenta eliminada'} · versión {ronda.version ?? '—'}</p>
                                            </div>
                                            {ronda.resumen_observacion && <TextSection title="Resumen de observación" text={ronda.resumen_observacion} />}
                                            {ronda.observaciones.map((observacion) => (
                                                <article key={observacion.id} className="space-y-1 border-t pt-3 text-sm">
                                                    <p className="font-medium">{observacion.categoria} · {observacion.titulo}{observacion.obligatoria ? ' · obligatoria' : ''}</p>
                                                    {observacion.seccion && <p className="text-xs text-muted-foreground">Sección: {observacion.seccion}</p>}
                                                    <p className="whitespace-pre-wrap text-muted-foreground">{observacion.descripcion}</p>
                                                    {observacion.respuesta && <p><span className="font-medium">Respuesta:</span> {observacion.respuesta}</p>}
                                                </article>
                                            ))}
                                            {ronda.resumen_correccion && <TextSection title="Resumen de corrección" text={ronda.resumen_correccion} />}
                                            {ronda.conclusion && <TextSection title="Conclusión o fundamento" text={ronda.conclusion} />}
                                            {ronda.comentario_publico && <TextSection title="Comentario público" text={ronda.comentario_publico} />}
                                            {ronda.comentario_interno && <TextSection title="Comentario interno" text={ronda.comentario_interno} />}
                                        </section>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {tramite.puede_gestionar_documentos_recepcion && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{tramite.estado === 'recibido_oficina' ? 'Completar digitalización' : 'Agregar documento independiente'}</CardTitle>
                                    <CardDescription>El archivo nuevo se conservará junto con los documentos existentes.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        action={TramiteController.upload.url({ tramite: tramite.id })}
                                        method="post"
                                        options={{ preserveScroll: true }}
                                        resetOnSuccess
                                        className="grid gap-3 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto] sm:items-end"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="categoria-documento">Categoría</Label>
                                                    <Select name="categoria" defaultValue={tramite.documentos.length === 0 ? 'documento_original' : 'documento_escaneado'}>
                                                        <SelectTrigger id="categoria-documento" className="w-full"><SelectValue /></SelectTrigger>
                                                        <SelectContent><SelectGroup>
                                                            <SelectItem value="documento_original">Documento original</SelectItem>
                                                            <SelectItem value="documento_escaneado">Documento escaneado</SelectItem>
                                                        </SelectGroup></SelectContent>
                                                    </Select>
                                                    <InputError message={errors.categoria} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="documento-digitalizado">Documento recibido</Label>
                                                    <Input
                                                        id="documento-digitalizado"
                                                        name="documento"
                                                        type="file"
                                                        required
                                                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                    />
                                                    <p className="text-xs text-muted-foreground">PDF, JPG o PNG; máximo 10 MB.</p>
                                                    <InputError message={errors.documento} />
                                                </div>
                                                <Button type="submit" disabled={processing}>
                                                    {processing ? <Spinner /> : <FileText />}
                                                    {tramite.estado === 'recibido_oficina' ? 'Digitalizar' : 'Agregar'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}

                        {tramite.puede_registrar_subsanacion && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Registrar subsanación física</CardTitle>
                                    <CardDescription>Adjunta el documento corregido recibido en Mesa de Partes. El estado seguirá Observado.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form action={TramiteController.correct.url({ tramite: tramite.id })} method="post" resetOnSuccess options={{ preserveScroll: true }} className="grid gap-3">
                                        {({ errors, processing }) => (
                                            <>
                                                <Label htmlFor="documento-subsanacion">Documento corregido</Label>
                                                <Input id="documento-subsanacion" name="documento" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                                                <InputError message={errors.documento} />
                                                <Label htmlFor="observacion-subsanacion">Detalle de la subsanación recibida</Label>
                                                <Textarea id="observacion-subsanacion" name="observacion" required minLength={3} maxLength={2000} rows={3} />
                                                <InputError message={errors.observacion} />
                                                <Button type="submit" disabled={processing}>Registrar subsanación</Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Documentos</CardTitle>
                                <CardDescription>Los archivos se mantienen en almacenamiento privado y se descargan con autorización.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {tramite.documentos.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Este trámite todavía no tiene un documento digitalizado.
                                    </div>
                                ) : (
                                    <ul className="divide-y">
                                        {tramite.documentos.map((documento) => (
                                            <li key={documento.id} className="flex flex-col justify-between gap-3 py-3 sm:flex-row sm:items-center">
                                                <div className="flex min-w-0 items-start gap-3">
                                                    <FileText className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">{documento.nombre_original}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {documento.categoria.replaceAll('_', ' ')} · {formatBytes(documento.tamano_bytes)} · versión {documento.version}
                                                            {documento.vigente ? ' · vigente' : ` · reemplazado por una versión posterior`}
                                                            {documento.documento_anterior_id ? ` · reemplaza #${documento.documento_anterior_id}` : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                                <a
                                                    className="inline-flex h-8 shrink-0 items-center justify-center gap-2 rounded-2xl border px-3 text-sm font-medium transition hover:bg-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/30"
                                                    href={TramiteController.download.url({ tramite: tramite.id, documento: documento.id })}
                                                >
                                                    <Download className="size-4" />
                                                    Descargar
                                                </a>
                                                {tramite.puede_gestionar_documentos_recepcion && documento.vigente && ['documento_original', 'documento_escaneado'].includes(documento.categoria) && (
                                                    <Form action={TramiteController.replace.url({ tramite: tramite.id, documento: documento.id })} method="post" resetOnSuccess options={{ preserveScroll: true }} className="flex flex-wrap items-end gap-2">
                                                        {({ errors, processing }) => (
                                                            <>
                                                                <div className="grid gap-1">
                                                                    <Label htmlFor={`reemplazo-${documento.id}`}>Nueva versión</Label>
                                                                    <Input id={`reemplazo-${documento.id}`} name="documento" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                                                                    <InputError message={errors.documento} />
                                                                </div>
                                                                <Button type="submit" variant="outline" disabled={processing}>Reemplazar</Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Historial del expediente</CardTitle>
                            <CardDescription>Registro de recepción, digitalización y descargas.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="space-y-5" aria-label="Historial del trámite">
                                {tramite.eventos.map((evento) => (
                                    <li key={evento.id} className="relative border-l pl-4 last:border-transparent">
                                        <span className="absolute -left-1 top-1 size-2 rounded-full bg-primary" />
                                        <p className="text-sm font-medium">{evento.descripcion}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {evento.usuario ?? 'Cuenta desactivada'} · {evento.created_at ? formatDateTime(evento.created_at) : ''}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm leading-6">{value}</p>
        </div>
    );
}

function TextSection({ title, text }: { title: string; text: string }) {
    return (
        <section className="space-y-2">
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="whitespace-pre-wrap text-sm leading-6">{text}</p>
        </section>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'long' }).format(new Date(`${value}T12:00:00`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function formatBytes(value: number): string {
    if (value < 1024) {
        return `${value} B`;
    }

    return `${(value / 1024 / 1024).toFixed(2)} MB`;
}

TramiteShow.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Detalle', href: '#' },
    ],
};
