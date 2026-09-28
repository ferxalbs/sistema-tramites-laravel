import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, FilePlus2, Save, Send } from 'lucide-react';
import TramiteBorradorController from '@/actions/App/Http/Controllers/TramiteBorradorController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type TramiteSummary = {
    id: number;
    codigo: string;
    asunto: string;
    fecha_recepcion: string;
};

type Plantilla = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    modalidad: string | null;
    requiere_firma_fisica: boolean;
    permite_no_firma: boolean;
};

type StaffUser = {
    id: number;
    name: string;
    rol: string;
};

type Recipient = {
    nombres: string;
    apellidos: string;
    cargo: string;
    correo: string;
    principal: boolean;
};

type MentionedPerson = {
    nombres: string;
    apellidos: string;
    cargo: string;
};

type ExistingDraft = {
    plantilla_id: number;
    remitente_id: number | null;
    firmante_id: number | null;
    fecha_documento: string;
    lugar: string;
    asunto: string;
    introduccion: string | null;
    contenido_principal: string | null;
    cierre: string | null;
    destinatarios: Recipient[] | null;
    personas_mencionadas: MentionedPerson[] | null;
    adjuntos: number[];
    version: number;
    estado: string;
};

type DraftVersion = {
    id: number;
    version: number;
    estado: string;
    actual: boolean;
    plantilla: string;
    created_at: string | null;
};

type Attachment = {
    id: number;
    nombre: string;
    categoria: string;
    version: number;
};

type ReviewObservation = {
    id: number;
    categoria: string;
    titulo: string;
    descripcion: string;
    seccion: string | null;
    obligatoria: boolean;
    respuesta: string | null;
};

type DraftForm = {
    plantilla_id: number;
    remitente_id: number | null;
    firmante_id: number | null;
    fecha_documento: string;
    lugar: string;
    asunto: string;
    introduccion: string;
    contenido_principal: string;
    cierre: string;
    destinatarios: Recipient[];
    personas_mencionadas: MentionedPerson[];
    adjuntos: number[];
    preparar: boolean;
    confirmar_fecha_anterior: boolean;
    resumen_correccion: string;
    respuestas: Record<number, string>;
};

type Props = {
    modo: 'borrador' | 'corregir';
    tramite: TramiteSummary;
    hoy: string;
    plantillas: Plantilla[];
    usuarios: StaffUser[];
    borrador: ExistingDraft | null;
    archivos: Attachment[];
    versiones: DraftVersion[];
    resumen_observacion?: string | null;
    observaciones?: ReviewObservation[];
};

export default function TramiteBorrador({ modo, tramite, hoy, plantillas, usuarios, borrador, archivos, versiones, resumen_observacion, observaciones = [] }: Props) {
    const form = useForm<DraftForm>({
        plantilla_id: borrador?.plantilla_id ?? plantillas[0]?.id ?? 0,
        remitente_id: borrador?.remitente_id ?? usuarios[0]?.id ?? null,
        firmante_id: borrador?.firmante_id ?? usuarios[0]?.id ?? null,
        fecha_documento: borrador?.fecha_documento ?? hoy,
        lugar: borrador?.lugar ?? 'Lima',
        asunto: borrador?.asunto ?? tramite.asunto,
        introduccion: borrador?.introduccion ?? '',
        contenido_principal: borrador?.contenido_principal ?? '',
        cierre: borrador?.cierre ?? 'Sin otro particular, quedo de usted.',
        destinatarios: borrador?.destinatarios?.length
            ? borrador.destinatarios
            : [{ nombres: '', apellidos: '', cargo: '', correo: '', principal: true }],
        personas_mencionadas: borrador?.personas_mencionadas ?? [],
        adjuntos: borrador?.adjuntos ?? [],
        preparar: false,
        confirmar_fecha_anterior: false,
        resumen_correccion: '',
        respuestas: Object.fromEntries(observaciones.map((observacion) => [observacion.id, observacion.respuesta ?? ''])),
    });

    const plantillaActual = plantillas.find((plantilla) => plantilla.id === form.data.plantilla_id);
    const destinatariosTexto = form.data.destinatarios
        .map((destinatario) => `${destinatario.nombres} ${destinatario.apellidos}`.trim())
        .filter(Boolean)
        .join(', ');
    const fechaAnterior = form.data.fecha_documento < tramite.fecha_recepcion;
    const erroresRespuesta = form.errors as Record<string, string | undefined>;
    const storeUrl = TramiteBorradorController.store.url({ tramite: tramite.id });
    const correctionUrl = TramiteBorradorController.correct.url({ tramite: tramite.id });

    function guardar(preparar: boolean) {
        form.transform((data) => ({ ...data, preparar: modo === 'corregir' || preparar }));
        form.post(modo === 'corregir' ? correctionUrl : storeUrl, { preserveScroll: true });
    }

    function actualizarDestinatario(indice: number, campo: keyof Recipient, valor: string | boolean) {
        form.setData('destinatarios', form.data.destinatarios.map((destinatario, posicion) => (
            posicion === indice ? { ...destinatario, [campo]: valor } : destinatario
        )));
    }

    function actualizarPersonaMencionada(indice: number, campo: keyof MentionedPerson, valor: string) {
        form.setData('personas_mencionadas', form.data.personas_mencionadas.map((persona, posicion) => (
            posicion === indice ? { ...persona, [campo]: valor } : persona
        )));
    }

    function alternarAdjunto(id: number, seleccionado: boolean) {
        form.setData('adjuntos', seleccionado
            ? [...new Set([...form.data.adjuntos, id])]
            : form.data.adjuntos.filter((archivoId) => archivoId !== id));
    }

    return (
        <>
            <Head title={`${modo === 'corregir' ? 'Corregir borrador' : 'Borrador'} ${tramite.codigo}`} />

            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
                        <Button render={<Link href={TramiteController.show({ tramite: tramite.id })} />} variant="outline" size="icon" aria-label="Volver al trámite">
                            <ArrowLeft />
                        </Button>
                        <div className="space-y-1">
                            <p className="text-sm text-muted-foreground">{tramite.codigo} · {modo === 'corregir' ? 'Corrección de revisión' : 'Preparación documental'}</p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {modo === 'corregir' ? `Corregir y reenviar desde la versión ${borrador?.version ?? ''}` : borrador ? `Nueva versión del borrador ${borrador.version + 1}` : 'Preparar borrador'}
                            </h1>
                            <p className="text-sm text-muted-foreground">{tramite.asunto}</p>
                        </div>
                    </div>
                </header>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,1fr)]">
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            guardar(false);
                        }}
                    >
                        {modo === 'corregir' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Observaciones del revisor</CardTitle>
                                    <CardDescription>{resumen_observacion}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {observaciones.map((observacion) => (
                                        <div key={observacion.id} className="space-y-2 rounded-xl border p-4">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{observacion.categoria}{observacion.seccion ? ` · ${observacion.seccion}` : ''}{observacion.obligatoria ? ' · respuesta obligatoria' : ''}</p>
                                            <h3 className="font-medium">{observacion.titulo}</h3>
                                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">{observacion.descripcion}</p>
                                            <Field id={`respuesta-${observacion.id}`} label="Respuesta a la observación" error={erroresRespuesta[`respuestas.${observacion.id}`]}>
                                                <textarea
                                                    id={`respuesta-${observacion.id}`}
                                                    rows={3}
                                                    minLength={observacion.obligatoria ? 5 : undefined}
                                                    required={observacion.obligatoria}
                                                    maxLength={2000}
                                                    className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                                    value={form.data.respuestas[observacion.id] ?? ''}
                                                    onChange={(event) => form.setData('respuestas', { ...form.data.respuestas, [observacion.id]: event.target.value })}
                                                />
                                            </Field>
                                        </div>
                                    ))}
                                    {observaciones.length === 0 && <p className="text-sm text-muted-foreground">No hay observaciones para responder.</p>}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Plantilla y responsables</CardTitle>
                                <CardDescription>{modo === 'corregir' ? 'La plantilla y su versión se conservan. Los cambios se guardarán como una versión nueva.' : 'El borrador se guarda como una nueva versión y no reserva numeración oficial.'}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                <FormSelect
                                    id="plantilla_id"
                                    label="Plantilla publicada"
                                    value={form.data.plantilla_id}
                                    options={plantillas.map((plantilla) => ({ value: plantilla.id, label: plantilla.nombre }))}
                                    error={form.errors.plantilla_id}
                                    onValueChange={(value) => form.setData('plantilla_id', value ?? 0)}
                                    disabled={modo === 'corregir'}
                                />
                                <FormSelect
                                    id="remitente_id"
                                    label="Remitente"
                                    value={form.data.remitente_id ?? 0}
                                    options={usuarios.map((usuario) => ({ value: usuario.id, label: `${usuario.name} · ${usuario.rol}` }))}
                                    error={form.errors.remitente_id}
                                    onValueChange={(value) => form.setData('remitente_id', value || null)}
                                />
                                <FormSelect
                                    id="firmante_id"
                                    label="Firmante propuesto"
                                    value={form.data.firmante_id ?? 0}
                                    options={usuarios.map((usuario) => ({ value: usuario.id, label: `${usuario.name} · ${usuario.rol}` }))}
                                    error={form.errors.firmante_id}
                                    onValueChange={(value) => form.setData('firmante_id', value || null)}
                                />
                                <Field id="fecha_documento" label="Fecha del documento" error={form.errors.fecha_documento}>
                                    <Input
                                        id="fecha_documento"
                                        type="date"
                                        value={form.data.fecha_documento}
                                        onChange={(event) => form.setData('fecha_documento', event.target.value)}
                                    />
                                </Field>
                                <Field id="lugar" label="Lugar" error={form.errors.lugar}>
                                    <Input id="lugar" value={form.data.lugar} maxLength={80} onChange={(event) => form.setData('lugar', event.target.value)} />
                                </Field>
                                <Field id="asunto" label="Asunto" error={form.errors.asunto}>
                                    <Input id="asunto" value={form.data.asunto} maxLength={255} onChange={(event) => form.setData('asunto', event.target.value)} />
                                </Field>
                                {plantillaActual && (
                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        {plantillaActual.descripcion} {plantillaActual.requiere_firma_fisica ? 'Requiere firma física.' : plantillaActual.permite_no_firma ? 'Puede entregarse sin firma física.' : ''}
                                    </p>
                                )}
                                {plantillas.length === 0 && (
                                    <p className="text-sm text-destructive sm:col-span-2">No hay plantillas activas disponibles para preparar el borrador.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Destinatarios</CardTitle>
                                <CardDescription>El memorando múltiple requiere al menos dos destinatarios y uno principal.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {form.data.destinatarios.map((destinatario, indice) => (
                                    <div key={indice} className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                                        <Field id={`destinatario-${indice}-nombres`} label="Nombres" error={form.errors[`destinatarios.${indice}.nombres`]}>
                                            <Input
                                                id={`destinatario-${indice}-nombres`}
                                                value={destinatario.nombres}
                                                maxLength={120}
                                                onChange={(event) => actualizarDestinatario(indice, 'nombres', event.target.value)}
                                            />
                                        </Field>
                                        <Field id={`destinatario-${indice}-apellidos`} label="Apellidos" error={form.errors[`destinatarios.${indice}.apellidos`]}>
                                            <Input
                                                id={`destinatario-${indice}-apellidos`}
                                                value={destinatario.apellidos}
                                                maxLength={120}
                                                onChange={(event) => actualizarDestinatario(indice, 'apellidos', event.target.value)}
                                            />
                                        </Field>
                                        <Field id={`destinatario-${indice}-cargo`} label="Cargo o función" error={form.errors[`destinatarios.${indice}.cargo`]}>
                                            <Input
                                                id={`destinatario-${indice}-cargo`}
                                                value={destinatario.cargo}
                                                maxLength={160}
                                                onChange={(event) => actualizarDestinatario(indice, 'cargo', event.target.value)}
                                            />
                                        </Field>
                                        <Field id={`destinatario-${indice}-correo`} label="Correo (opcional)" error={form.errors[`destinatarios.${indice}.correo`]}>
                                            <Input
                                                id={`destinatario-${indice}-correo`}
                                                type="email"
                                                value={destinatario.correo}
                                                maxLength={190}
                                                onChange={(event) => actualizarDestinatario(indice, 'correo', event.target.value)}
                                            />
                                        </Field>
                                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                            <input
                                                type="checkbox"
                                                checked={destinatario.principal}
                                                onChange={(event) => actualizarDestinatario(indice, 'principal', event.target.checked)}
                                            />
                                            Destinatario principal
                                        </label>
                                    </div>
                                ))}
                                <InputError message={form.errors.destinatarios} />
                                {plantillaActual?.modalidad === 'multiple' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.data.destinatarios.length >= 20}
                                        onClick={() => form.setData('destinatarios', [
                                            ...form.data.destinatarios,
                                            { nombres: '', apellidos: '', cargo: '', correo: '', principal: false },
                                        ])}
                                    >
                                        <FilePlus2 />
                                        Añadir destinatario
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contenido del borrador</CardTitle>
                                <CardDescription>La vista previa es provisional y no incluye firma ni número oficial.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <Field id="introduccion" label="Introducción" error={form.errors.introduccion}>
                                    <textarea
                                        id="introduccion"
                                        rows={3}
                                        maxLength={5000}
                                        className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                        value={form.data.introduccion}
                                        onChange={(event) => form.setData('introduccion', event.target.value)}
                                    />
                                </Field>
                                <Field id="contenido_principal" label="Contenido principal" error={form.errors.contenido_principal}>
                                    <textarea
                                        id="contenido_principal"
                                        rows={8}
                                        maxLength={20000}
                                        className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                        value={form.data.contenido_principal}
                                        onChange={(event) => form.setData('contenido_principal', event.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground">Al marcar como preparado, escribe al menos 20 caracteres.</p>
                                </Field>
                                <Field id="cierre" label="Cierre" error={form.errors.cierre}>
                                    <textarea
                                        id="cierre"
                                        rows={3}
                                        maxLength={5000}
                                        className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                        value={form.data.cierre}
                                        onChange={(event) => form.setData('cierre', event.target.value)}
                                    />
                                </Field>
                                {fechaAnterior && (
                                    <label className="flex items-start gap-2 rounded-xl border border-amber-500/30 p-3 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.confirmar_fecha_anterior}
                                            onChange={(event) => form.setData('confirmar_fecha_anterior', event.target.checked)}
                                        />
                                        Confirmo que la fecha del documento es anterior a la recepción.
                                    </label>
                                )}
                                <InputError message={form.errors.confirmar_fecha_anterior} />
                            </CardContent>
                        </Card>

                        {modo === 'corregir' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Resumen de correcciones</CardTitle>
                                    <CardDescription>Describe los cambios realizados antes de volver a enviar el borrador al mismo revisor.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Field id="resumen_correccion" label="Resumen" error={form.errors.resumen_correccion}>
                                        <textarea id="resumen_correccion" required minLength={8} maxLength={2000} rows={4} className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30" value={form.data.resumen_correccion} onChange={(event) => form.setData('resumen_correccion', event.target.value)} />
                                    </Field>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Personas mencionadas</CardTitle>
                                <CardDescription>Registra a las personas que aparecerán en el documento.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {form.data.personas_mencionadas.map((persona, indice) => (
                                    <div key={indice} className="grid gap-4 rounded-xl border p-4 sm:grid-cols-3">
                                        <Field id={`persona-${indice}-nombres`} label="Nombres" error={form.errors[`personas_mencionadas.${indice}.nombres`]}>
                                            <Input
                                                id={`persona-${indice}-nombres`}
                                                value={persona.nombres}
                                                maxLength={120}
                                                onChange={(event) => actualizarPersonaMencionada(indice, 'nombres', event.target.value)}
                                            />
                                        </Field>
                                        <Field id={`persona-${indice}-apellidos`} label="Apellidos" error={form.errors[`personas_mencionadas.${indice}.apellidos`]}>
                                            <Input
                                                id={`persona-${indice}-apellidos`}
                                                value={persona.apellidos}
                                                maxLength={120}
                                                onChange={(event) => actualizarPersonaMencionada(indice, 'apellidos', event.target.value)}
                                            />
                                        </Field>
                                        <Field id={`persona-${indice}-cargo`} label="Cargo o función" error={form.errors[`personas_mencionadas.${indice}.cargo`]}>
                                            <Input
                                                id={`persona-${indice}-cargo`}
                                                value={persona.cargo}
                                                maxLength={160}
                                                onChange={(event) => actualizarPersonaMencionada(indice, 'cargo', event.target.value)}
                                            />
                                        </Field>
                                    </div>
                                ))}
                                <InputError message={form.errors.personas_mencionadas} />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.data.personas_mencionadas.length >= 20}
                                    onClick={() => form.setData('personas_mencionadas', [
                                        ...form.data.personas_mencionadas,
                                        { nombres: '', apellidos: '', cargo: '' },
                                    ])}
                                >
                                    <FilePlus2 />
                                    Añadir persona
                                </Button>
                            </CardContent>
                        </Card>

                        {archivos.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Documentos adjuntos</CardTitle>
                                    <CardDescription>Selecciona los archivos del expediente que se mencionarán en el borrador.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {archivos.map((archivo) => (
                                        <label key={archivo.id} className="flex items-center gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={form.data.adjuntos.includes(archivo.id)}
                                                onChange={(event) => alternarAdjunto(archivo.id, event.target.checked)}
                                            />
                                            <span>{archivo.nombre} · versión {archivo.version}</span>
                                        </label>
                                    ))}
                                    <InputError message={form.errors.adjuntos} />
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                            <Button render={<Link href={TramiteController.show({ tramite: tramite.id })} />} variant="outline">
                                Cancelar
                            </Button>
                            {modo === 'corregir' ? (
                                <Button type="submit" disabled={form.processing || plantillas.length === 0}>
                                    {form.processing ? <Spinner /> : <Send />}
                                    Guardar corrección y reenviar
                                </Button>
                            ) : (
                                <>
                                    <Button type="submit" variant="outline" disabled={form.processing || plantillas.length === 0}>
                                        {form.processing ? <Spinner /> : <Save />}
                                        Guardar borrador
                                    </Button>
                                    <Button type="button" disabled={form.processing || plantillas.length === 0} onClick={() => guardar(true)}>
                                        {form.processing ? <Spinner /> : <Send />}
                                        Marcar preparado
                                    </Button>
                                </>
                            )}
                        </div>
                    </form>

                    <aside className="space-y-5">
                        <Card className="h-fit">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2"><Eye className="size-4" /> Vista previa</CardTitle>
                                <CardDescription>Borrador sin numeración oficial</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <p className="font-semibold">{plantillaActual?.nombre ?? 'Sin plantilla seleccionada'}</p>
                                <p><span className="font-medium">A:</span> {destinatariosTexto || 'Destinatario pendiente'}</p>
                                <p><span className="font-medium">De:</span> {usuarios.find((usuario) => usuario.id === form.data.remitente_id)?.name ?? 'Remitente pendiente'}</p>
                                <p><span className="font-medium">Asunto:</span> {form.data.asunto || 'Sin asunto'}</p>
                                <p><span className="font-medium">Fecha:</span> {form.data.lugar}, {formatDate(form.data.fecha_documento)}</p>
                                <p className="whitespace-pre-wrap">{form.data.introduccion}</p>
                                <p className="whitespace-pre-wrap">{form.data.contenido_principal || 'El contenido principal aparecerá aquí.'}</p>
                                <p className="whitespace-pre-wrap">{form.data.cierre}</p>
                                <p className="border-t pt-3 font-medium">{usuarios.find((usuario) => usuario.id === form.data.firmante_id)?.name ?? 'Firmante pendiente'}</p>
                            </CardContent>
                        </Card>

                        {versiones.length > 0 && (
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle>Historial de versiones</CardTitle>
                                    <CardDescription>Las versiones anteriores se conservan al guardar cambios.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ol className="space-y-3">
                                        {versiones.map((version) => (
                                            <li key={version.id} className="flex items-start justify-between gap-3 text-sm">
                                                <div>
                                                    <p className="font-medium">Versión {version.version}{version.actual ? ' · actual' : ''}</p>
                                                    <p className="text-muted-foreground">{version.plantilla} · {version.estado.replaceAll('_', ' ')}</p>
                                                </div>
                                                <time className="shrink-0 text-xs text-muted-foreground">
                                                    {version.created_at ? formatDateTime(version.created_at) : ''}
                                                </time>
                                            </li>
                                        ))}
                                    </ol>
                                </CardContent>
                            </Card>
                        )}
                    </aside>
                </div>
            </main>
        </>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function FormSelect({
    id,
    label,
    value,
    options,
    error,
    onValueChange,
    disabled = false,
}: {
    id: string;
    label: string;
    value: number;
    options: Array<{ value: number; label: string }>;
    error?: string;
    onValueChange: (value: number | null) => void;
    disabled?: boolean;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select items={options} name={id} value={value || null} onValueChange={onValueChange} disabled={disabled}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder={`Selecciona ${label.toLocaleLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

function formatDate(value: string): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'long' }).format(new Date(`${value.slice(0, 10)}T12:00:00`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

TramiteBorrador.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Detalle', href: '#' },
        { title: 'Borrador', href: '#' },
    ],
};
