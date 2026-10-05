import { Head, Link, useForm } from '@inertiajs/react';
import {
    Children,
    cloneElement,
    isValidElement,
    useState,
    type ReactElement,
} from 'react';
import { ArrowLeft, Eye, FilePlus2, Save, Send } from 'lucide-react';
import TramiteBorradorController from '@/actions/App/Http/Controllers/TramiteBorradorController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Textarea } from '@/components/ui/textarea';
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
    campos: CampoPlantilla[];
};

type CampoPlantilla = {
    clave: string;
    etiqueta: string;
    tipo: string;
    obligatorio: boolean;
    maximo: number;
    predeterminado: string;
    ayuda: string | null;
};

type StaffUser = {
    id: number;
    name: string;
    rol: string;
    cargo: string;
    firma_registrada: boolean;
};

type Recipient = {
    nombres: string;
    apellidos: string;
    cargo: string;
    correo: string;
    principal: boolean;
};

type RecipientSuggestion = Omit<Recipient, 'principal'> & {
    key: string;
    label: string;
};

type MentionedPerson = {
    nombres: string;
    apellidos: string;
    cargo: string;
    dni: string;
};

type ExistingDraft = {
    id: number;
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
    campos: Record<string, string>;
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
    campos: Record<string, string>;
};

type Props = {
    modo: 'borrador' | 'corregir';
    tramite: TramiteSummary;
    hoy: string;
    modelo_oficial_pendiente?: boolean;
    plantillas: Plantilla[];
    usuarios: StaffUser[];
    destinatarios_sugeridos: RecipientSuggestion[];
    borrador: ExistingDraft | null;
    archivos: Attachment[];
    versiones: DraftVersion[];
    resumen_observacion?: string | null;
    observaciones?: ReviewObservation[];
};

export default function TramiteBorrador({
    modo,
    tramite,
    hoy,
    modelo_oficial_pendiente = false,
    plantillas,
    usuarios,
    destinatarios_sugeridos,
    borrador,
    archivos,
    versiones,
    resumen_observacion,
    observaciones = [],
}: Props) {
    const [destinatariosElegidos, setDestinatariosElegidos] = useState<
        Record<number, string>
    >({});
    const form = useForm<DraftForm>({
        plantilla_id:
            borrador?.plantilla_id ??
            (plantillas.length === 1 ? plantillas[0].id : 0),
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
            : [
                  {
                      nombres: '',
                      apellidos: '',
                      cargo: '',
                      correo: '',
                      principal: true,
                  },
              ],
        personas_mencionadas:
            borrador?.personas_mencionadas?.map((persona) => ({
                ...persona,
                dni: persona.dni ?? '',
            })) ?? [],
        adjuntos: borrador?.adjuntos ?? [],
        preparar: false,
        confirmar_fecha_anterior: false,
        resumen_correccion: '',
        respuestas: Object.fromEntries(
            observaciones.map((observacion) => [
                observacion.id,
                observacion.respuesta ?? '',
            ]),
        ),
        campos:
            borrador?.campos ??
            Object.fromEntries(
                (plantillas.length === 1 ? plantillas[0].campos : []).map(
                    (campo) => [campo.clave, campo.predeterminado],
                ),
            ),
    });

    const plantillaActual = plantillas.find(
        (plantilla) => plantilla.id === form.data.plantilla_id,
    );
    const fechaAnterior = form.data.fecha_documento < tramite.fecha_recepcion;
    const erroresRespuesta = form.errors as Record<string, string | undefined>;
    const storeUrl = TramiteBorradorController.store.url({
        tramite: tramite.id,
    });
    const correctionUrl = TramiteBorradorController.correct.url({
        tramite: tramite.id,
    });

    function guardar(preparar: boolean) {
        form.transform((data) => ({
            ...data,
            preparar: modo === 'corregir' || preparar,
        }));
        form.post(modo === 'corregir' ? correctionUrl : storeUrl, {
            preserveScroll: true,
        });
    }

    function actualizarDestinatario(
        indice: number,
        campo: keyof Recipient,
        valor: string | boolean,
    ) {
        form.setData(
            'destinatarios',
            form.data.destinatarios.map((destinatario, posicion) =>
                posicion === indice
                    ? { ...destinatario, [campo]: valor }
                    : destinatario,
            ),
        );
    }

    function aplicarDestinatario(indice: number, key: string | null) {
        if (!key) return;
        const sugerencia = destinatarios_sugeridos.find(
            (item) => item.key === key,
        );
        if (!sugerencia) return;
        setDestinatariosElegidos((actual) => ({ ...actual, [indice]: key }));
        form.setData(
            'destinatarios',
            form.data.destinatarios.map((destinatario, posicion) =>
                posicion === indice
                    ? {
                          ...destinatario,
                          nombres: sugerencia.nombres,
                          apellidos: sugerencia.apellidos,
                          cargo: sugerencia.cargo,
                          correo: sugerencia.correo,
                      }
                    : destinatario,
            ),
        );
    }

    function actualizarPersonaMencionada(
        indice: number,
        campo: keyof MentionedPerson,
        valor: string,
    ) {
        form.setData(
            'personas_mencionadas',
            form.data.personas_mencionadas.map((persona, posicion) =>
                posicion === indice ? { ...persona, [campo]: valor } : persona,
            ),
        );
    }

    function alternarAdjunto(id: number, seleccionado: boolean) {
        form.setData(
            'adjuntos',
            seleccionado
                ? [...new Set([...form.data.adjuntos, id])]
                : form.data.adjuntos.filter((archivoId) => archivoId !== id),
        );
    }

    return (
        <>
            <Head
                title={`${modo === 'corregir' ? 'Corregir borrador' : 'Borrador'} ${tramite.codigo}`}
            />

            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
                        <Button
                            render={
                                <Link
                                    href={TramiteController.show({
                                        tramite: tramite.id,
                                    })}
                                />
                            }
                            variant="outline"
                            size="icon"
                            aria-label="Volver al trámite"
                        >
                            <ArrowLeft />
                        </Button>
                        <div className="flex flex-col gap-1">
                            <p className="text-sm text-muted-foreground">
                                {tramite.codigo} ·{' '}
                                {modo === 'corregir'
                                    ? 'Corrección de revisión'
                                    : 'Preparación documental'}
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {modo === 'corregir'
                                    ? `Corregir y reenviar desde la versión ${borrador?.version ?? ''}`
                                    : borrador
                                      ? `Nueva versión del borrador ${borrador.version + 1}`
                                      : 'Preparar borrador'}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {tramite.asunto}
                            </p>
                        </div>
                    </div>
                </header>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,1fr)]">
                    <form
                        className="flex flex-col gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            guardar(false);
                        }}
                    >
                        {modo === 'corregir' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Observaciones del revisor
                                    </CardTitle>
                                    <CardDescription>
                                        {resumen_observacion}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    {observaciones.map((observacion) => (
                                        <div
                                            key={observacion.id}
                                            className="flex flex-col gap-2 rounded-xl border p-4"
                                        >
                                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                {observacion.categoria}
                                                {observacion.seccion
                                                    ? ` · ${observacion.seccion}`
                                                    : ''}
                                                {observacion.obligatoria
                                                    ? ' · respuesta obligatoria'
                                                    : ''}
                                            </p>
                                            <h3 className="font-medium">
                                                {observacion.titulo}
                                            </h3>
                                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                                {observacion.descripcion}
                                            </p>
                                            <Field
                                                id={`respuesta-${observacion.id}`}
                                                label="Respuesta a la observación"
                                                error={
                                                    erroresRespuesta[
                                                        `respuestas.${observacion.id}`
                                                    ]
                                                }
                                            >
                                                <Textarea
                                                    id={`respuesta-${observacion.id}`}
                                                    rows={3}
                                                    minLength={
                                                        observacion.obligatoria
                                                            ? 5
                                                            : undefined
                                                    }
                                                    required={
                                                        observacion.obligatoria
                                                    }
                                                    maxLength={2000}
                                                    value={
                                                        form.data.respuestas[
                                                            observacion.id
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'respuestas',
                                                            {
                                                                ...form.data
                                                                    .respuestas,
                                                                [observacion.id]:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    ))}
                                    {observaciones.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No hay observaciones para responder.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Plantilla y responsables</CardTitle>
                                <CardDescription>
                                    {modo === 'corregir'
                                        ? 'La plantilla y su versión se conservan. Los cambios se guardarán como una versión nueva.'
                                        : 'El borrador se guarda como una nueva versión y no reserva numeración oficial.'}
                                </CardDescription>
                                {modelo_oficial_pendiente && (
                                    <p className="text-sm text-muted-foreground">
                                        Este trámite aún no tiene un modelo
                                        oficial aprobado. Puedes preparar un
                                        borrador interno para revisión, pero la
                                        emisión del PDF final está bloqueada.
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="grid gap-5 sm:grid-cols-2">
                                    <FormSelect
                                        id="plantilla_id"
                                        label="Plantilla publicada"
                                        value={form.data.plantilla_id}
                                        options={plantillas.map(
                                            (plantilla) => ({
                                                value: plantilla.id,
                                                label: plantilla.nombre,
                                            }),
                                        )}
                                        error={form.errors.plantilla_id}
                                        onValueChange={(value) => {
                                            form.setData((previous) => ({
                                                ...previous,
                                                plantilla_id: value ?? 0,
                                                campos: Object.fromEntries(
                                                    (
                                                        plantillas.find(
                                                            (item) =>
                                                                item.id ===
                                                                value,
                                                        )?.campos ?? []
                                                    ).map((campo) => [
                                                        campo.clave,
                                                        campo.predeterminado,
                                                    ]),
                                                ),
                                            }));
                                        }}
                                        disabled={modo === 'corregir'}
                                    />
                                    <FormSelect
                                        id="remitente_id"
                                        label="Remitente"
                                        value={form.data.remitente_id ?? 0}
                                        options={usuarios.map((usuario) => ({
                                            value: usuario.id,
                                            label: `${usuario.name} · ${usuario.cargo}`,
                                        }))}
                                        error={form.errors.remitente_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'remitente_id',
                                                value || null,
                                            )
                                        }
                                    />
                                    <FormSelect
                                        id="firmante_id"
                                        label="Firmante del PDF"
                                        value={form.data.firmante_id ?? 0}
                                        options={usuarios.map((usuario) => ({
                                            value: usuario.id,
                                            label: `${usuario.name} · ${usuario.cargo}${usuario.firma_registrada ? '' : ' · falta registrar firma'}`,
                                        }))}
                                        error={form.errors.firmante_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'firmante_id',
                                                value || null,
                                            )
                                        }
                                    />
                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        {usuarios.find(
                                            (usuario) =>
                                                usuario.id ===
                                                form.data.firmante_id,
                                        )?.firma_registrada
                                            ? 'Se colocará la firma guardada en el perfil de la persona seleccionada.'
                                            : 'La persona seleccionada debe guardar su firma escaneada en Mi perfil antes de emitir el PDF.'}
                                    </p>
                                    <Field
                                        id="fecha_documento"
                                        label="Fecha del documento"
                                        error={form.errors.fecha_documento}
                                    >
                                        <Input
                                            id="fecha_documento"
                                            type="date"
                                            value={form.data.fecha_documento}
                                            onChange={(event) =>
                                                form.setData(
                                                    'fecha_documento',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="lugar"
                                        label="Lugar"
                                        error={form.errors.lugar}
                                    >
                                        <Input
                                            id="lugar"
                                            value={form.data.lugar}
                                            maxLength={80}
                                            onChange={(event) =>
                                                form.setData(
                                                    'lugar',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="asunto"
                                        label="Asunto"
                                        error={form.errors.asunto}
                                    >
                                        <Input
                                            id="asunto"
                                            value={form.data.asunto}
                                            maxLength={255}
                                            onChange={(event) =>
                                                form.setData(
                                                    'asunto',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    {plantillaActual && (
                                        <p className="text-sm text-muted-foreground sm:col-span-2">
                                            {plantillaActual.descripcion} El PDF
                                            incluirá la firma escaneada guardada
                                            en el perfil del firmante
                                            seleccionado.
                                        </p>
                                    )}
                                    {plantillas.length === 0 && (
                                        <p className="text-sm text-destructive sm:col-span-2">
                                            No hay plantillas activas
                                            disponibles para preparar el
                                            borrador.
                                        </p>
                                    )}
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Destinatarios</CardTitle>
                                <CardDescription>
                                    El memorando múltiple requiere al menos dos
                                    destinatarios y uno principal.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="gap-5">
                                    {form.data.destinatarios.map(
                                        (destinatario, indice) => (
                                            <div
                                                key={indice}
                                                className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                                            >
                                                <ShadcnField className="gap-2 sm:col-span-2">
                                                    <FieldLabel
                                                        htmlFor={`destinatario-${indice}-sugerido`}
                                                    >
                                                        Elegir destinatario
                                                        institucional o docente
                                                        (opcional)
                                                    </FieldLabel>
                                                    <Select
                                                        value={
                                                            destinatariosElegidos[
                                                                indice
                                                            ] ?? null
                                                        }
                                                        onValueChange={(key) =>
                                                            aplicarDestinatario(
                                                                indice,
                                                                key,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id={`destinatario-${indice}-sugerido`}
                                                        >
                                                            <SelectValue placeholder="Selecciona una persona o escribe los datos manualmente" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {destinatarios_sugeridos.map(
                                                                    (
                                                                        sugerencia,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                sugerencia.key
                                                                            }
                                                                            value={
                                                                                sugerencia.key
                                                                            }
                                                                        >
                                                                            {
                                                                                sugerencia.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                </ShadcnField>
                                                <Field
                                                    id={`destinatario-${indice}-nombres`}
                                                    label="Nombres"
                                                    error={
                                                        form.errors[
                                                            `destinatarios.${indice}.nombres`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`destinatario-${indice}-nombres`}
                                                        value={
                                                            destinatario.nombres
                                                        }
                                                        maxLength={120}
                                                        onChange={(event) =>
                                                            actualizarDestinatario(
                                                                indice,
                                                                'nombres',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`destinatario-${indice}-apellidos`}
                                                    label="Apellidos"
                                                    error={
                                                        form.errors[
                                                            `destinatarios.${indice}.apellidos`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`destinatario-${indice}-apellidos`}
                                                        value={
                                                            destinatario.apellidos
                                                        }
                                                        maxLength={120}
                                                        onChange={(event) =>
                                                            actualizarDestinatario(
                                                                indice,
                                                                'apellidos',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`destinatario-${indice}-cargo`}
                                                    label="Cargo o función"
                                                    error={
                                                        form.errors[
                                                            `destinatarios.${indice}.cargo`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`destinatario-${indice}-cargo`}
                                                        value={
                                                            destinatario.cargo
                                                        }
                                                        maxLength={160}
                                                        onChange={(event) =>
                                                            actualizarDestinatario(
                                                                indice,
                                                                'cargo',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`destinatario-${indice}-correo`}
                                                    label="Correo (opcional)"
                                                    error={
                                                        form.errors[
                                                            `destinatarios.${indice}.correo`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`destinatario-${indice}-correo`}
                                                        type="email"
                                                        value={
                                                            destinatario.correo
                                                        }
                                                        maxLength={190}
                                                        onChange={(event) =>
                                                            actualizarDestinatario(
                                                                indice,
                                                                'correo',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <ShadcnField
                                                    orientation="horizontal"
                                                    className="sm:col-span-2"
                                                    data-invalid={
                                                        form.errors
                                                            .destinatarios
                                                            ? true
                                                            : undefined
                                                    }
                                                >
                                                    <Checkbox
                                                        id={`destinatario-${indice}-principal`}
                                                        checked={
                                                            destinatario.principal
                                                        }
                                                        aria-invalid={
                                                            form.errors
                                                                .destinatarios
                                                                ? true
                                                                : undefined
                                                        }
                                                        aria-describedby={
                                                            form.errors
                                                                .destinatarios
                                                                ? 'destinatarios-error'
                                                                : undefined
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            actualizarDestinatario(
                                                                indice,
                                                                'principal',
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <FieldLabel
                                                        htmlFor={`destinatario-${indice}-principal`}
                                                    >
                                                        Destinatario principal
                                                    </FieldLabel>
                                                </ShadcnField>
                                            </div>
                                        ),
                                    )}
                                    <FieldError id="destinatarios-error">
                                        {form.errors.destinatarios}
                                    </FieldError>
                                    {plantillaActual?.modalidad ===
                                        'multiple' && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={
                                                form.data.destinatarios
                                                    .length >= 20
                                            }
                                            onClick={() =>
                                                form.setData('destinatarios', [
                                                    ...form.data.destinatarios,
                                                    {
                                                        nombres: '',
                                                        apellidos: '',
                                                        cargo: '',
                                                        correo: '',
                                                        principal: false,
                                                    },
                                                ])
                                            }
                                        >
                                            <FilePlus2 />
                                            Añadir destinatario
                                        </Button>
                                    )}
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contenido del borrador</CardTitle>
                                <CardDescription>
                                    La vista previa es provisional y no incluye
                                    firma ni número oficial.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="gap-5">
                                    <EditableTemplateFields
                                        campos={plantillaActual?.campos ?? []}
                                        valores={form.data.campos}
                                        errores={erroresRespuesta}
                                        onChange={(clave, valor) =>
                                            form.setData('campos', {
                                                ...form.data.campos,
                                                [clave]: valor,
                                            })
                                        }
                                    />
                                    <Field
                                        id="introduccion"
                                        label="Introducción"
                                        error={form.errors.introduccion}
                                    >
                                        <Textarea
                                            id="introduccion"
                                            rows={3}
                                            maxLength={5000}
                                            value={form.data.introduccion}
                                            onChange={(event) =>
                                                form.setData(
                                                    'introduccion',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="contenido_principal"
                                        label="Contenido principal"
                                        error={form.errors.contenido_principal}
                                    >
                                        <Textarea
                                            id="contenido_principal"
                                            rows={8}
                                            maxLength={20000}
                                            value={
                                                form.data.contenido_principal
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'contenido_principal',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Al marcar como preparado, escribe al
                                            menos 20 caracteres.
                                        </p>
                                    </Field>
                                    <Field
                                        id="cierre"
                                        label="Cierre"
                                        error={form.errors.cierre}
                                    >
                                        <Textarea
                                            id="cierre"
                                            rows={3}
                                            maxLength={5000}
                                            value={form.data.cierre}
                                            onChange={(event) =>
                                                form.setData(
                                                    'cierre',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    {fechaAnterior && (
                                        <ShadcnField
                                            data-invalid={
                                                form.errors
                                                    .confirmar_fecha_anterior
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <Alert>
                                                <Checkbox
                                                    id="confirmar-fecha-anterior"
                                                    checked={
                                                        form.data
                                                            .confirmar_fecha_anterior
                                                    }
                                                    aria-invalid={
                                                        form.errors
                                                            .confirmar_fecha_anterior
                                                            ? true
                                                            : undefined
                                                    }
                                                    aria-describedby={
                                                        form.errors
                                                            .confirmar_fecha_anterior
                                                            ? 'confirmar-fecha-anterior-error'
                                                            : undefined
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        form.setData(
                                                            'confirmar_fecha_anterior',
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <AlertDescription>
                                                    <FieldLabel htmlFor="confirmar-fecha-anterior">
                                                        Confirmo que la fecha
                                                        del documento es
                                                        anterior a la recepción.
                                                    </FieldLabel>
                                                </AlertDescription>
                                            </Alert>
                                            <FieldError id="confirmar-fecha-anterior-error">
                                                {
                                                    form.errors
                                                        .confirmar_fecha_anterior
                                                }
                                            </FieldError>
                                        </ShadcnField>
                                    )}
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        {modo === 'corregir' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Resumen de correcciones
                                    </CardTitle>
                                    <CardDescription>
                                        Describe los cambios realizados antes de
                                        volver a enviar el borrador al mismo
                                        revisor.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <Field
                                            id="resumen_correccion"
                                            label="Resumen"
                                            error={
                                                form.errors.resumen_correccion
                                            }
                                        >
                                            <Textarea
                                                id="resumen_correccion"
                                                required
                                                minLength={8}
                                                maxLength={2000}
                                                rows={4}
                                                value={
                                                    form.data.resumen_correccion
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'resumen_correccion',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Personas mencionadas</CardTitle>
                                <CardDescription>
                                    Registra a las personas que aparecerán en el
                                    documento.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="gap-4">
                                    {form.data.personas_mencionadas.map(
                                        (persona, indice) => (
                                            <div
                                                key={indice}
                                                className="grid gap-4 rounded-xl border p-4 sm:grid-cols-4"
                                            >
                                                <Field
                                                    id={`persona-${indice}-nombres`}
                                                    label="Nombres"
                                                    error={
                                                        form.errors[
                                                            `personas_mencionadas.${indice}.nombres`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`persona-${indice}-nombres`}
                                                        value={persona.nombres}
                                                        maxLength={120}
                                                        onChange={(event) =>
                                                            actualizarPersonaMencionada(
                                                                indice,
                                                                'nombres',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`persona-${indice}-apellidos`}
                                                    label="Apellidos"
                                                    error={
                                                        form.errors[
                                                            `personas_mencionadas.${indice}.apellidos`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`persona-${indice}-apellidos`}
                                                        value={
                                                            persona.apellidos
                                                        }
                                                        maxLength={120}
                                                        onChange={(event) =>
                                                            actualizarPersonaMencionada(
                                                                indice,
                                                                'apellidos',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`persona-${indice}-cargo`}
                                                    label="Cargo o función"
                                                    error={
                                                        form.errors[
                                                            `personas_mencionadas.${indice}.cargo`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`persona-${indice}-cargo`}
                                                        value={persona.cargo}
                                                        maxLength={160}
                                                        onChange={(event) =>
                                                            actualizarPersonaMencionada(
                                                                indice,
                                                                'cargo',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id={`persona-${indice}-dni`}
                                                    label="DNI (opcional)"
                                                    error={
                                                        form.errors[
                                                            `personas_mencionadas.${indice}.dni`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`persona-${indice}-dni`}
                                                        inputMode="numeric"
                                                        maxLength={8}
                                                        value={persona.dni}
                                                        onChange={(event) =>
                                                            actualizarPersonaMencionada(
                                                                indice,
                                                                'dni',
                                                                event.target.value
                                                                    .replace(
                                                                        /\D/g,
                                                                        '',
                                                                    )
                                                                    .slice(
                                                                        0,
                                                                        8,
                                                                    ),
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                        ),
                                    )}
                                    <FieldError id="personas-mencionadas-error">
                                        {form.errors.personas_mencionadas}
                                    </FieldError>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            form.data.personas_mencionadas
                                                .length >= 20
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'personas_mencionadas',
                                                [
                                                    ...form.data
                                                        .personas_mencionadas,
                                                    {
                                                        nombres: '',
                                                        apellidos: '',
                                                        cargo: '',
                                                        dni: '',
                                                    },
                                                ],
                                            )
                                        }
                                    >
                                        <FilePlus2 />
                                        Añadir persona
                                    </Button>
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        {archivos.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Documentos adjuntos</CardTitle>
                                    <CardDescription>
                                        Selecciona los archivos del expediente
                                        que se mencionarán en el borrador.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup className="gap-3">
                                        {archivos.map((archivo) => (
                                            <ShadcnField
                                                key={archivo.id}
                                                orientation="horizontal"
                                                data-invalid={
                                                    form.errors.adjuntos
                                                        ? true
                                                        : undefined
                                                }
                                            >
                                                <Checkbox
                                                    id={`adjunto-${archivo.id}`}
                                                    checked={form.data.adjuntos.includes(
                                                        archivo.id,
                                                    )}
                                                    aria-invalid={
                                                        form.errors.adjuntos
                                                            ? true
                                                            : undefined
                                                    }
                                                    aria-describedby={
                                                        form.errors.adjuntos
                                                            ? 'adjuntos-error'
                                                            : undefined
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        alternarAdjunto(
                                                            archivo.id,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <FieldLabel
                                                    htmlFor={`adjunto-${archivo.id}`}
                                                >
                                                    {archivo.nombre} · versión{' '}
                                                    {archivo.version}
                                                </FieldLabel>
                                            </ShadcnField>
                                        ))}
                                        <FieldError id="adjuntos-error">
                                            {form.errors.adjuntos}
                                        </FieldError>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                            <Button
                                render={
                                    <Link
                                        href={TramiteController.show({
                                            tramite: tramite.id,
                                        })}
                                    />
                                }
                                variant="outline"
                            >
                                Cancelar
                            </Button>
                            {modo === 'corregir' ? (
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        plantillas.length === 0
                                    }
                                >
                                    {form.processing ? <Spinner /> : <Send />}
                                    Guardar corrección y reenviar
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            form.processing ||
                                            plantillas.length === 0 ||
                                            !form.data.plantilla_id
                                        }
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save />
                                        )}
                                        Guardar borrador
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={
                                            form.processing ||
                                            plantillas.length === 0 ||
                                            !form.data.plantilla_id
                                        }
                                        onClick={() => guardar(true)}
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Send />
                                        )}
                                        Marcar preparado
                                    </Button>
                                </>
                            )}
                        </div>
                    </form>

                    <aside className="flex flex-col gap-5">
                        <Card className="h-fit">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Eye className="size-4" /> Vista previa
                                </CardTitle>
                                <CardDescription>
                                    PDF institucional sin reservar numeración
                                    oficial
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                {borrador?.id ? (
                                    <iframe
                                        title="Vista previa PDF del borrador"
                                        src={`/tramites/${tramite.id}/borradores/${borrador.id}/pdf`}
                                        sandbox="allow-same-origin"
                                        className="h-[720px] w-full rounded-md border bg-muted"
                                    />
                                ) : (
                                    <div className="flex flex-col gap-2 text-sm text-muted-foreground">
                                        <p>
                                            Guarda el borrador para generar una
                                            vista previa PDF con el encabezado
                                            institucional.
                                        </p>
                                        <p>
                                            Los cambios sin guardar se mantienen
                                            en el formulario y no reservan
                                            numeración oficial.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {versiones.length > 0 && (
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle>
                                        Historial de versiones
                                    </CardTitle>
                                    <CardDescription>
                                        Las versiones anteriores se conservan al
                                        guardar cambios.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ol className="flex flex-col gap-3">
                                        {versiones.map((version) => (
                                            <li
                                                key={version.id}
                                                className="flex items-start justify-between gap-3 text-sm"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        Versión{' '}
                                                        {version.version}
                                                        {version.actual
                                                            ? ' · actual'
                                                            : ''}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {version.plantilla} ·{' '}
                                                        {version.estado.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </p>
                                                </div>
                                                <time className="shrink-0 text-xs text-muted-foreground">
                                                    {version.created_at
                                                        ? formatDateTime(
                                                              version.created_at,
                                                          )
                                                        : ''}
                                                </time>
                                                <Link
                                                    className={buttonVariants({
                                                        variant: 'outline',
                                                        size: 'sm',
                                                    })}
                                                    href={TramiteBorradorController.show(
                                                        {
                                                            tramite: tramite.id,
                                                            borrador:
                                                                version.id,
                                                        },
                                                    )}
                                                >
                                                    Vista previa
                                                </Link>
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

function EditableTemplateFields({
    campos,
    valores,
    errores,
    onChange,
}: {
    campos: CampoPlantilla[];
    valores: Record<string, string>;
    errores: Record<string, string | undefined>;
    onChange: (clave: string, valor: string) => void;
}) {
    return campos.map((campo) => (
        <Field
            key={campo.clave}
            id={`campo-${campo.clave}`}
            label={campo.etiqueta}
            error={errores[`campos.${campo.clave}`]}
        >
            {['texto_largo', 'texto_enriquecido'].includes(campo.tipo) ? (
                <Textarea
                    id={`campo-${campo.clave}`}
                    rows={3}
                    maxLength={campo.maximo}
                    value={valores[campo.clave] ?? ''}
                    onChange={(event) =>
                        onChange(campo.clave, event.target.value)
                    }
                />
            ) : (
                <Input
                    id={`campo-${campo.clave}`}
                    type={
                        campo.tipo === 'fecha'
                            ? 'date'
                            : campo.tipo === 'numero'
                              ? 'number'
                              : 'text'
                    }
                    maxLength={campo.maximo}
                    value={valores[campo.clave] ?? ''}
                    onChange={(event) =>
                        onChange(campo.clave, event.target.value)
                    }
                />
            )}
            {campo.ayuda && (
                <p className="text-xs text-muted-foreground">{campo.ayuda}</p>
            )}
        </Field>
    ));
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
    const errorId = `${id}-error`;
    const invalid = Boolean(error);

    return (
        <ShadcnField
            data-invalid={invalid ? true : undefined}
            data-disabled={disabled ? true : undefined}
        >
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Select
                items={options}
                name={id}
                value={value || null}
                onValueChange={onValueChange}
                disabled={disabled}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-describedby={invalid ? errorId : undefined}
                    aria-invalid={invalid ? true : undefined}
                >
                    <SelectValue
                        placeholder={`Selecciona ${label.toLocaleLowerCase()}`}
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            <FieldError id={errorId}>{error}</FieldError>
        </ShadcnField>
    );
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

TramiteBorrador.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Detalle', href: '#' },
        { title: 'Borrador', href: '#' },
    ],
};
