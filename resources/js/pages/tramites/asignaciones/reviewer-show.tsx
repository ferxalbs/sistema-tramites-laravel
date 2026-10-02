import { Form, Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Plus, Send } from 'lucide-react';
import {
    Children,
    cloneElement,
    isValidElement,
    type ReactElement,
} from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteDocumentoFinalController from '@/actions/App/Http/Controllers/TramiteDocumentoFinalController';
import TramiteRevisionController from '@/actions/App/Http/Controllers/TramiteRevisionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field as ShadcnField,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Recipient = {
    nombres: string;
    apellidos: string | null;
    cargo: string | null;
    correo: string | null;
    principal: boolean;
};
type MentionedPerson = {
    nombres: string;
    apellidos: string | null;
    cargo: string | null;
    dni?: string | null;
};
type ObservationDraft = {
    categoria: string;
    titulo: string;
    descripcion: string;
    seccion: string;
    obligatoria: boolean;
    visible_para_interesado: boolean;
};
type Observation = ObservationDraft & {
    id: number;
    respuesta: string | null;
};
type ReviewRound = {
    numero: number;
    estado: string;
    version: number | null;
    resumen_observacion: string | null;
    resumen_correccion: string | null;
    conclusion: string | null;
    comentario_publico: string | null;
    comentario_interno: string | null;
    observaciones: Observation[];
};
type Props = {
    destino: 'docente' | 'oficina';
    categorias_observacion: string[];
    tramite: {
        id: number;
        codigo: string;
        asunto: string;
        persona_nombre: string | null;
        persona_identificador: string | null;
        descripcion: string | null;
        fecha_recepcion: string;
        prioridad: string;
        estado: string;
        estado_label: string;
    };
    asignacion: {
        id: number;
        destino: string;
        motivo: string;
        instrucciones_revision: string | null;
        fecha_esperada: string | null;
        fecha_asignacion: string | null;
        asignado_por: string | null;
    };
    borrador: {
        version: number;
        plantilla: string;
        remitente: string | null;
        firmante: string | null;
        fecha_documento: string;
        lugar: string;
        asunto: string;
        introduccion: string | null;
        contenido_principal: string | null;
        contenido_renderizado: string | null;
        cierre: string | null;
        destinatarios: Recipient[];
        personas_mencionadas: MentionedPerson[];
        adjuntos: Array<{
            id: number;
            nombre: string;
            categoria: string;
            version: number;
        }>;
    };
    archivos: Array<{
        id: number;
        nombre: string;
        categoria: string;
        version: number;
        vigente: boolean;
    }>;
    documentos_finales: Array<{
        id: number;
        numero: string;
        estado: string;
        version: number;
    }>;
    revision: {
        puede_iniciar: boolean;
        puede_observar: boolean;
        puede_decidir: boolean;
        rondas: ReviewRound[];
    };
};

export default function ReviewerShow({
    destino,
    categorias_observacion,
    tramite,
    asignacion,
    borrador,
    archivos,
    documentos_finales,
    revision,
}: Props) {
    const back =
        destino === 'docente'
            ? TramiteAsignacionController.docenteIndex()
            : TramiteAsignacionController.oficinaIndex();
    const observationForm = useForm({
        resumen: '',
        observaciones: [
            {
                categoria: categorias_observacion[0] ?? 'Otro',
                titulo: '',
                descripcion: '',
                seccion: '',
                obligatoria: true,
                visible_para_interesado: false,
            },
        ] as ObservationDraft[],
    });

    function actualizarObservacion(
        index: number,
        key: keyof ObservationDraft,
        value: string | boolean,
    ) {
        observationForm.setData(
            'observaciones',
            observationForm.data.observaciones.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [key]: value } : item,
            ),
        );
    }

    function enviarObservaciones(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        observationForm.post(
            TramiteRevisionController.observe.url({ tramite: tramite.id }),
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Expediente ${tramite.codigo}`} />
            <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button
                        render={<Link href={back} />}
                        variant="outline"
                        size="icon"
                        aria-label="Volver a asignaciones"
                    >
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            Revisión asignada ·{' '}
                            {destino === 'docente' ? 'Docente' : 'Oficina'}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {tramite.codigo}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tramite.asunto}
                        </p>
                    </div>
                </header>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>Expediente</CardTitle>
                                <CardDescription>
                                    Información de recepción vinculada a este
                                    trámite.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                {tramite.persona_nombre !== null && (
                                    <Detail
                                        label="Solicitante"
                                        value={tramite.persona_nombre}
                                    />
                                )}
                                <Detail
                                    label="Identificador"
                                    value={
                                        tramite.persona_identificador ??
                                        'No registrado'
                                    }
                                />
                                <Detail
                                    label="Recepción"
                                    value={formatDate(tramite.fecha_recepcion)}
                                />
                                <Detail
                                    label="Prioridad"
                                    value={tramite.prioridad}
                                />
                                <Detail
                                    label="Estado"
                                    value={tramite.estado_label}
                                />
                                <div className="sm:col-span-2">
                                    <Detail
                                        label="Descripción"
                                        value={
                                            tramite.descripcion ??
                                            'Sin descripción'
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {borrador.plantilla} · Versión{' '}
                                    {borrador.version}
                                </CardTitle>
                                <CardDescription>
                                    La ronda conserva la versión revisada. El
                                    borrador todavía no tiene numeración
                                    oficial.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {borrador.contenido_renderizado && (
                                    <section>
                                        <h3>Vista del borrador revisado</h3>
                                        <p className="whitespace-pre-wrap">
                                            {borrador.contenido_renderizado}
                                        </p>
                                    </section>
                                )}
                                <Detail
                                    label="Destinatario(s)"
                                    value={
                                        borrador.destinatarios
                                            .map(
                                                (recipient) =>
                                                    `${recipient.nombres} ${recipient.apellidos ?? ''}${recipient.cargo ? ` · ${recipient.cargo}` : ''}`,
                                            )
                                            .join('; ') || 'No registrado'
                                    }
                                />
                                <Detail
                                    label="Remitente"
                                    value={
                                        borrador.remitente ?? 'No registrado'
                                    }
                                />
                                <Detail
                                    label="Asunto"
                                    value={borrador.asunto}
                                />
                                <Detail
                                    label="Lugar y fecha"
                                    value={`${borrador.lugar}, ${formatDate(borrador.fecha_documento)}`}
                                />
                                {borrador.introduccion && (
                                    <TextSection
                                        title="Introducción"
                                        text={borrador.introduccion}
                                    />
                                )}
                                <TextSection
                                    title="Contenido principal"
                                    text={
                                        borrador.contenido_principal ??
                                        'Sin contenido'
                                    }
                                />
                                {borrador.cierre && (
                                    <TextSection
                                        title="Cierre"
                                        text={borrador.cierre}
                                    />
                                )}
                                <Detail
                                    label="Firmante propuesto"
                                    value={borrador.firmante ?? 'No registrado'}
                                />
                                {borrador.personas_mencionadas.length > 0 && (
                                    <div className="space-y-2">
                                        <h3 className="text-sm font-medium">
                                            Personas mencionadas
                                        </h3>
                                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                                            {borrador.personas_mencionadas.map(
                                                (person) => (
                                                    <li
                                                        key={[
                                                            person.nombres,
                                                            person.apellidos ??
                                                                '',
                                                            person.cargo ?? '',
                                                            person.dni ?? '',
                                                        ].join('|')}
                                                    >
                                                        {person.nombres}{' '}
                                                        {person.apellidos ?? ''}
                                                        {person.cargo
                                                            ? ` · ${person.cargo}`
                                                            : ''}
                                                        {person.dni
                                                            ? ` · DNI ${person.dni}`
                                                            : ''}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5" /> Archivos del
                                    expediente
                                </CardTitle>
                                <CardDescription>
                                    Los documentos se mantienen privados; las
                                    rutas de almacenamiento no se muestran.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {archivos.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No hay archivos adjuntos.
                                    </p>
                                ) : (
                                    <ul className="space-y-2 text-sm">
                                        {archivos.map((archivo) => (
                                            <li
                                                key={archivo.id}
                                                className="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <span>
                                                    {archivo.nombre} · versión{' '}
                                                    {archivo.version}
                                                    {archivo.vigente
                                                        ? ''
                                                        : ' · reemplazado'}
                                                </span>
                                                <a
                                                    href={TramiteController.download.url(
                                                        {
                                                            tramite: tramite.id,
                                                            documento:
                                                                archivo.id,
                                                        },
                                                    )}
                                                    className={buttonVariants({
                                                        variant: 'outline',
                                                        size: 'sm',
                                                    })}
                                                >
                                                    <Download data-icon="inline-start" />{' '}
                                                    Descargar
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {documentos_finales.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Documentos oficiales revisados
                                    </CardTitle>
                                    <CardDescription>
                                        Las versiones anteriores permanecen
                                        privadas y conservan su número.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm">
                                        {documentos_finales.map((documento) => (
                                            <li
                                                key={documento.id}
                                                className="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <span>
                                                    {documento.numero} · versión{' '}
                                                    {documento.version} ·{' '}
                                                    {documento.estado}
                                                </span>
                                                <a
                                                    href={TramiteDocumentoFinalController.download.url(
                                                        {
                                                            tramite: tramite.id,
                                                            documento:
                                                                documento.id,
                                                        },
                                                    )}
                                                    className={buttonVariants({
                                                        variant: 'outline',
                                                        size: 'sm',
                                                    })}
                                                >
                                                    <Download data-icon="inline-start" />{' '}
                                                    Descargar PDF
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {revision.rondas.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Historial de revisión</CardTitle>
                                    <CardDescription>
                                        Las observaciones y decisiones de cada
                                        ronda se conservan.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {revision.rondas.map((ronda) => (
                                        <section
                                            key={ronda.numero}
                                            className="space-y-3 rounded-xl border p-4"
                                        >
                                            <div className="flex flex-wrap justify-between gap-2 text-sm">
                                                <h3 className="font-semibold">
                                                    Ronda {ronda.numero} ·{' '}
                                                    {ronda.estado.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </h3>
                                                {ronda.version !== null && (
                                                    <span className="text-muted-foreground">
                                                        Versión revisada{' '}
                                                        {ronda.version}
                                                    </span>
                                                )}
                                            </div>
                                            {ronda.resumen_observacion && (
                                                <TextSection
                                                    title="Resumen de observación"
                                                    text={
                                                        ronda.resumen_observacion
                                                    }
                                                />
                                            )}
                                            {ronda.observaciones.map(
                                                (observacion) => (
                                                    <article
                                                        key={observacion.id}
                                                        className="border-t pt-3 text-sm"
                                                    >
                                                        <p className="font-medium">
                                                            {
                                                                observacion.categoria
                                                            }{' '}
                                                            ·{' '}
                                                            {observacion.titulo}
                                                            {observacion.obligatoria
                                                                ? ' · obligatoria'
                                                                : ''}
                                                        </p>
                                                        {observacion.seccion && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Sección:{' '}
                                                                {
                                                                    observacion.seccion
                                                                }
                                                            </p>
                                                        )}
                                                        <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                                            {
                                                                observacion.descripcion
                                                            }
                                                        </p>
                                                        {observacion.respuesta && (
                                                            <p className="mt-2">
                                                                <span className="font-medium">
                                                                    Respuesta:
                                                                </span>{' '}
                                                                {
                                                                    observacion.respuesta
                                                                }
                                                            </p>
                                                        )}
                                                    </article>
                                                ),
                                            )}
                                            {ronda.resumen_correccion && (
                                                <TextSection
                                                    title="Resumen de corrección"
                                                    text={
                                                        ronda.resumen_correccion
                                                    }
                                                />
                                            )}
                                            {ronda.conclusion && (
                                                <TextSection
                                                    title="Conclusión o fundamento"
                                                    text={ronda.conclusion}
                                                />
                                            )}
                                            {ronda.comentario_publico && (
                                                <TextSection
                                                    title="Comentario público"
                                                    text={
                                                        ronda.comentario_publico
                                                    }
                                                />
                                            )}
                                            {ronda.comentario_interno && (
                                                <TextSection
                                                    title="Comentario interno"
                                                    text={
                                                        ronda.comentario_interno
                                                    }
                                                />
                                            )}
                                        </section>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {revision.puede_observar && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Registrar observaciones
                                    </CardTitle>
                                    <CardDescription>
                                        Las observaciones quedan ligadas a esta
                                        ronda y a la versión revisada.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="space-y-5"
                                        onSubmit={enviarObservaciones}
                                    >
                                        <FieldGroup className="gap-5">
                                            <Field
                                                id="resumen"
                                                label="Resumen de la observación"
                                                error={
                                                    observationForm.errors
                                                        .resumen
                                                }
                                            >
                                                <Textarea
                                                    id="resumen"
                                                    required
                                                    minLength={8}
                                                    maxLength={2000}
                                                    rows={3}
                                                    value={
                                                        observationForm.data
                                                            .resumen
                                                    }
                                                    onChange={(event) =>
                                                        observationForm.setData(
                                                            'resumen',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            {observationForm.data.observaciones.map(
                                                (observacion, index) => (
                                                    <FieldGroup
                                                        key={index}
                                                        className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                                                    >
                                                        <Field
                                                            id={`observacion-${index}-categoria`}
                                                            label="Categoría"
                                                            error={
                                                                observationForm
                                                                    .errors[
                                                                    `observaciones.${index}.categoria`
                                                                ]
                                                            }
                                                        >
                                                            <Select
                                                                items={categorias_observacion.map(
                                                                    (
                                                                        categoria,
                                                                    ) => ({
                                                                        value: categoria,
                                                                        label: categoria,
                                                                    }),
                                                                )}
                                                                value={
                                                                    observacion.categoria
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    value &&
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'categoria',
                                                                        value,
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger
                                                                    id={`observacion-${index}-categoria`}
                                                                    className="w-full"
                                                                    aria-describedby={
                                                                        observationForm
                                                                            .errors[
                                                                            `observaciones.${index}.categoria`
                                                                        ]
                                                                            ? `observacion-${index}-categoria-error`
                                                                            : undefined
                                                                    }
                                                                    aria-invalid={
                                                                        observationForm
                                                                            .errors[
                                                                            `observaciones.${index}.categoria`
                                                                        ]
                                                                            ? true
                                                                            : undefined
                                                                    }
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectGroup>
                                                                        {categorias_observacion.map(
                                                                            (
                                                                                categoria,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        categoria
                                                                                    }
                                                                                    value={
                                                                                        categoria
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        categoria
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectGroup>
                                                                </SelectContent>
                                                            </Select>
                                                        </Field>
                                                        <Field
                                                            id={`observacion-${index}-titulo`}
                                                            label="Título"
                                                            error={
                                                                observationForm
                                                                    .errors[
                                                                    `observaciones.${index}.titulo`
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                id={`observacion-${index}-titulo`}
                                                                value={
                                                                    observacion.titulo
                                                                }
                                                                maxLength={160}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'titulo',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`observacion-${index}-seccion`}
                                                            label="Sección del documento"
                                                            error={
                                                                observationForm
                                                                    .errors[
                                                                    `observaciones.${index}.seccion`
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                id={`observacion-${index}-seccion`}
                                                                value={
                                                                    observacion.seccion
                                                                }
                                                                maxLength={160}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'seccion',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <Field
                                                            id={`observacion-${index}-descripcion`}
                                                            label="Descripción"
                                                            error={
                                                                observationForm
                                                                    .errors[
                                                                    `observaciones.${index}.descripcion`
                                                                ]
                                                            }
                                                        >
                                                            <Textarea
                                                                id={`observacion-${index}-descripcion`}
                                                                required
                                                                minLength={5}
                                                                maxLength={5000}
                                                                rows={3}
                                                                value={
                                                                    observacion.descripcion
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'descripcion',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                        <ShadcnField
                                                            orientation="horizontal"
                                                            className="sm:col-span-2"
                                                        >
                                                            <Checkbox
                                                                id={`observacion-${index}-obligatoria`}
                                                                checked={
                                                                    observacion.obligatoria
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'obligatoria',
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <FieldLabel
                                                                htmlFor={`observacion-${index}-obligatoria`}
                                                            >
                                                                Requiere
                                                                respuesta antes
                                                                de reenviar
                                                            </FieldLabel>
                                                        </ShadcnField>
                                                        <ShadcnField
                                                            orientation="horizontal"
                                                            className="sm:col-span-2"
                                                        >
                                                            <Checkbox
                                                                id={`observacion-${index}-visible`}
                                                                checked={
                                                                    observacion.visible_para_interesado
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    actualizarObservacion(
                                                                        index,
                                                                        'visible_para_interesado',
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <FieldLabel
                                                                htmlFor={`observacion-${index}-visible`}
                                                            >
                                                                Compartir con el
                                                                estudiante
                                                            </FieldLabel>
                                                        </ShadcnField>
                                                    </FieldGroup>
                                                ),
                                            )}
                                            <FieldError id="observaciones-error">
                                                {
                                                    observationForm.errors
                                                        .observaciones
                                                }
                                            </FieldError>
                                            <div className="flex flex-wrap justify-between gap-3">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={
                                                        observationForm.data
                                                            .observaciones
                                                            .length >= 20
                                                    }
                                                    onClick={() =>
                                                        observationForm.setData(
                                                            'observaciones',
                                                            [
                                                                ...observationForm
                                                                    .data
                                                                    .observaciones,
                                                                {
                                                                    categoria:
                                                                        categorias_observacion[0] ??
                                                                        'Otro',
                                                                    titulo: '',
                                                                    descripcion:
                                                                        '',
                                                                    seccion: '',
                                                                    obligatoria: true,
                                                                    visible_para_interesado: false,
                                                                },
                                                            ],
                                                        )
                                                    }
                                                >
                                                    <Plus /> Añadir observación
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        observationForm.processing
                                                    }
                                                >
                                                    {observationForm.processing ? (
                                                        <Spinner />
                                                    ) : (
                                                        <Send />
                                                    )}{' '}
                                                    Registrar observaciones
                                                </Button>
                                            </div>
                                        </FieldGroup>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <aside className="space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>Asignación</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <Detail
                                    label="Motivo"
                                    value={asignacion.motivo}
                                />
                                <Detail
                                    label="Instrucciones"
                                    value={
                                        asignacion.instrucciones_revision ??
                                        'Sin instrucciones adicionales'
                                    }
                                />
                                <Detail
                                    label="Fecha esperada"
                                    value={
                                        asignacion.fecha_esperada
                                            ? formatDate(
                                                  asignacion.fecha_esperada,
                                              )
                                            : 'No definida'
                                    }
                                />
                                <Detail
                                    label="Asignado por"
                                    value={
                                        asignacion.asignado_por ??
                                        'Cuenta eliminada'
                                    }
                                />
                                {revision.puede_iniciar && (
                                    <Form
                                        action={TramiteRevisionController.start.url(
                                            { tramite: tramite.id },
                                        )}
                                        method="post"
                                        className="border-t pt-4"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Send />
                                                )}{' '}
                                                {tramite.estado === 'corregido'
                                                    ? 'Iniciar nueva ronda'
                                                    : 'Iniciar revisión'}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                                {!revision.puede_iniciar &&
                                    !revision.puede_decidir && (
                                        <p className="border-t pt-4 text-xs text-muted-foreground">
                                            Estado: {tramite.estado_label}.
                                        </p>
                                    )}
                            </CardContent>
                        </Card>

                        {revision.puede_decidir && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Decisión de revisión</CardTitle>
                                    <CardDescription>
                                        Las decisiones se registran en la ronda
                                        activa y finalizan la asignación.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup className="gap-5">
                                        <Form
                                            action={TramiteRevisionController.decide.url(
                                                { tramite: tramite.id },
                                            )}
                                            method="post"
                                            className="space-y-3"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="decision"
                                                        value="aprobar"
                                                    />
                                                    <Field
                                                        id="conclusion-aprobacion"
                                                        label="Conclusión para aprobar"
                                                        error={
                                                            errors.conclusion
                                                        }
                                                    >
                                                        <Textarea
                                                            id="conclusion-aprobacion"
                                                            name="conclusion"
                                                            required
                                                            minLength={8}
                                                            maxLength={4000}
                                                            rows={3}
                                                        />
                                                    </Field>
                                                    <Field
                                                        id="comentario-publico-aprobacion"
                                                        label="Mensaje para el estudiante (opcional)"
                                                        error={
                                                            errors.comentario_publico
                                                        }
                                                    >
                                                        <Textarea
                                                            id="comentario-publico-aprobacion"
                                                            name="comentario_publico"
                                                            maxLength={4000}
                                                            rows={2}
                                                        />
                                                    </Field>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="w-full"
                                                    >
                                                        {processing ? (
                                                            <Spinner />
                                                        ) : null}{' '}
                                                        Aprobar trámite
                                                    </Button>
                                                </>
                                            )}
                                        </Form>

                                        <Form
                                            action={TramiteRevisionController.decide.url(
                                                { tramite: tramite.id },
                                            )}
                                            method="post"
                                            className="space-y-3 border-t pt-5"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="decision"
                                                        value="rechazar"
                                                    />
                                                    <Field
                                                        id="fundamento-rechazo"
                                                        label="Fundamento del rechazo"
                                                        error={
                                                            errors.conclusion
                                                        }
                                                    >
                                                        <Textarea
                                                            id="fundamento-rechazo"
                                                            name="conclusion"
                                                            required
                                                            minLength={8}
                                                            maxLength={4000}
                                                            rows={3}
                                                        />
                                                    </Field>
                                                    <Field
                                                        id="comentario-publico-rechazo"
                                                        label="Comentario público"
                                                        error={
                                                            errors.comentario_publico
                                                        }
                                                    >
                                                        <Textarea
                                                            id="comentario-publico-rechazo"
                                                            name="comentario_publico"
                                                            required
                                                            minLength={8}
                                                            maxLength={4000}
                                                            rows={3}
                                                        />
                                                    </Field>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        disabled={processing}
                                                        className="w-full"
                                                    >
                                                        {processing ? (
                                                            <Spinner />
                                                        ) : null}{' '}
                                                        Rechazar trámite
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </FieldGroup>
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
    const errorId = `${id}-error`;
    const invalid = Boolean(error);
    const control = Children.map(children, (child, index) => {
        if (index !== 0 || !isValidElement(child)) {
            return child;
        }

        return cloneElement(
            child as ReactElement<{
                id?: string;
                'aria-describedby'?: string;
                'aria-invalid'?: boolean;
            }>,
            {
                id,
                'aria-describedby': invalid ? errorId : undefined,
                'aria-invalid': invalid ? true : undefined,
            },
        );
    });

    return (
        <ShadcnField data-invalid={invalid ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {control}
            <FieldError id={errorId}>{error}</FieldError>
        </ShadcnField>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm">{value}</p>
        </div>
    );
}

function TextSection({ title, text }: { title: string; text: string }) {
    return (
        <section className="space-y-2">
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="text-sm leading-6 whitespace-pre-wrap">{text}</p>
        </section>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00Z`));
}

ReviewerShow.layout = {
    breadcrumbs: [
        { title: 'Asignaciones', href: '#' },
        { title: 'Expediente', href: '#' },
    ],
};
