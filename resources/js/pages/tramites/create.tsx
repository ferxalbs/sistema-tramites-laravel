import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FilePlus2, Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import ReceptionPreliminaryLists from '@/components/reception-preliminary-lists';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    SelectGroup,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type Catalogos = {
    clasificaciones: Record<string, string>;
    tipos_documento: Record<string, Record<string, string>>;
    formatos_salida: Record<string, string>;
    modalidades_documento: Record<string, string>;
    formatos_sugeridos: Record<string, string>;
    requisitos_tipo: Record<string, {
        requiere_solicitante: boolean;
        modalidad_documento: string | null;
        personas_relacionadas: boolean;
        destinatarios_multiples: boolean;
        documento_original: boolean;
    }>;
    tipos_relacion: Record<string, string>;
    destinos: Record<string, string>;
    prioridades: Record<string, string>;
};

type Props = {
    catalogos: Catalogos;
    ahora: string;
    estudiantes: Array<{ id: number; name: string; dni: string | null; email: string; celular: string | null; correo_alternativo: string | null }>;
    docentes: Array<{ id: number; name: string; dni: string | null }>;
    programas: Array<{ id: number; nombre: string }>;
    cargos_institucionales: Array<{ id: number; nombre: string }>;
    seleccion?: {
        tipo_documento: string;
        tipo_nombre: string;
        clasificacion: string;
        requiere_solicitante: boolean;
        dni: string | null;
        formato_salida: string | null;
        modalidad_documento: string | null;
    } | null;
    tramite: {
        id: number;
        codigo: string;
        clasificacion: string;
        tipo_documento: string;
        formato_salida: string | null;
        modalidad_documento: string | null;
        persona_nombre: string | null;
        persona_identificador: string | null;
        propietario_id: number | null;
        programa_estudio_id: number | null;
        destino_tipo: string;
        destino_nombre: string;
        destino_docente_id: number | null;
        asunto: string;
        descripcion: string | null;
        prioridad: string;
        fecha_llegada_oficina: string;
        fecha_presentacion_original: string | null;
        numero_expediente_externo: string | null;
        area_procedencia: string | null;
        persona_entrega_documento: string | null;
        observacion_recepcion: string | null;
        folios: number | null;
        personas_relacionadas: Array<{ nombres: string; apellidos: string | null; dni: string | null; cargo_funcion: string | null; tipo_relacion: string }>;
        destinatarios: Array<{ nombres: string; apellidos: string | null; cargo_institucional_id: number | null; cargo_texto: string | null; correo_institucional: string | null }>;
        personas_mencionadas: Array<{ nombres: string; apellidos: string | null; dni: string | null; cargo_funcion: string | null; descripcion: string | null }>;
    } | null;
};

function searchPeople<T extends { id: number; name: string; dni: string | null }>(
    people: T[],
    query: string,
    selectedId: string | null,
): T[] {
    const text = query.trim().toLocaleLowerCase();
    const digits = query.replace(/\D/g, '');

    return people.filter((person) =>
        String(person.id) === selectedId
        || person.name.toLocaleLowerCase().includes(text)
        || (digits !== '' && (person.dni ?? '').includes(digits)),
    );
}

export default function TramiteCreate({ catalogos, ahora, estudiantes, docentes, programas, cargos_institucionales, tramite, seleccion = null }: Props) {
    const primeraClasificacion = tramite?.clasificacion ?? seleccion?.clasificacion ?? Object.keys(catalogos.clasificaciones)[0] ?? 'estudiantil';
    const tipoInicial = tramite?.tipo_documento ?? seleccion?.tipo_documento ?? Object.keys(catalogos.tipos_documento[primeraClasificacion] ?? {})[0] ?? '';
    const estudianteInicial = estudiantes.find((estudiante) => estudiante.id === tramite?.propietario_id)
        ?? estudiantes.find((estudiante) => estudiante.dni === seleccion?.dni);
    const [clasificacion, setClasificacion] = useState(primeraClasificacion);
    const [tipoDocumento, setTipoDocumento] = useState(tipoInicial);
    const [formatoSalida, setFormatoSalida] = useState(tramite?.formato_salida ?? seleccion?.formato_salida ?? catalogos.formatos_sugeridos[tipoInicial] ?? 'pendiente');
    const [modalidadDocumento, setModalidadDocumento] = useState(tramite?.modalidad_documento ?? seleccion?.modalidad_documento ?? catalogos.requisitos_tipo[tipoInicial]?.modalidad_documento ?? '');
    const [destinoTipo, setDestinoTipo] = useState(tramite?.destino_tipo ?? 'oficina');
    const [destinoDocenteId, setDestinoDocenteId] = useState<string | null>(tramite?.destino_docente_id ? String(tramite.destino_docente_id) : null);
    const [prioridad, setPrioridad] = useState(tramite?.prioridad ?? 'normal');
    const [propietarioId, setPropietarioId] = useState<string | null>(estudianteInicial ? String(estudianteInicial.id) : null);
    const [dniEstudiante, setDniEstudiante] = useState(estudianteInicial?.dni ?? seleccion?.dni ?? tramite?.persona_identificador ?? '');
    const [buscarDocente, setBuscarDocente] = useState('');
    const [documentos, setDocumentos] = useState<Array<{ id: number; categoria: string }>>([]);
    const siguienteDocumentoId = useRef(0);
    const formatoObligatorio = catalogos.formatos_sugeridos[tipoDocumento];
    const modalidadSugerida = catalogos.requisitos_tipo[tipoDocumento]?.modalidad_documento ?? null;
    const requiereSolicitante = seleccion?.requiere_solicitante ?? catalogos.requisitos_tipo[tipoDocumento]?.requiere_solicitante ?? true;
    const esDocumentoInstitucional = !requiereSolicitante;
    const estudianteSeleccionado = estudiantes.find((estudiante) => String(estudiante.id) === propietarioId) ?? null;
    const docentesFiltrados = searchPeople(docentes, buscarDocente, destinoDocenteId);

    function changeTipoDocumento(nextType: string) {
        setTipoDocumento(nextType);
        const suggested = catalogos.formatos_sugeridos[nextType];
        if (suggested) {
            setFormatoSalida(suggested);
            setModalidadDocumento(catalogos.requisitos_tipo[nextType]?.modalidad_documento ?? '');
        } else {
            setModalidadDocumento('');
        }
    }

    function changeClasificacion(value: string | null) {
        const nextClassification = value ?? primeraClasificacion;
        setClasificacion(nextClassification);
        if (nextClassification !== 'estudiantil') {
            setPropietarioId(null);
            setDniEstudiante('');
        }
        changeTipoDocumento(Object.keys(catalogos.tipos_documento[nextClassification] ?? {})[0] ?? '');
    }

    function changeDniEstudiante(value: string) {
        const dni = value.replace(/\D/g, '').slice(0, 8);
        const match = dni.length === 8 ? estudiantes.find((estudiante) => estudiante.dni === dni) : undefined;
        setDniEstudiante(dni);
        setPropietarioId(match ? String(match.id) : null);
    }

    function changeFormatoSalida(value: string | null) {
        const nextFormat = value ?? 'pendiente';
        setFormatoSalida(nextFormat);
        if (nextFormat !== 'memorando') setModalidadDocumento('');
    }

    function changeDestinoTipo(value: string | null) {
        setDestinoTipo(value ?? 'oficina');
        setDestinoDocenteId(null);
    }

    return (
        <>
            <Head title={tramite ? `Editar ${tramite.codigo}` : 'Registrar trámite'} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button render={<Link href={tramite ? TramiteController.show({ tramite: tramite.id }) : TramiteController.index()} />} variant="outline" size="icon" aria-label="Volver">
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">{esDocumentoInstitucional ? 'Documento institucional' : 'Recepción y digitalización'}</p>
                        <h1 className="text-2xl font-semibold tracking-tight">{tramite ? `Editar ${tramite.codigo}` : seleccion ? `Digitalizar: ${seleccion.tipo_nombre}` : 'Registrar trámite'}</h1>
                        <p className="text-sm text-muted-foreground">
                            {tramite
                                ? 'Corrige los datos transcritos y de recepción. El código interno, el estado, los archivos y el historial se conservan.'
                                : seleccion
                                    ? esDocumentoInstitucional
                                        ? 'Registra los datos y adjunta el documento institucional. No se requieren datos de solicitante.'
                                        : 'Completa los datos que corresponden a este tipo de solicitud y adjunta el documento recibido.'
                                    : 'Selecciona el DNI y el tipo de trámite para abrir el formulario de digitalización.'}
                        </p>
                    </div>
                </header>

                <Form
                    action={tramite ? TramiteController.update.url({ tramite: tramite.id }) : TramiteController.store.url()}
                    method={tramite ? 'put' : 'post'}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>{seleccion
                                        ? esDocumentoInstitucional ? 'Documento institucional: ' + seleccion.tipo_nombre : 'Solicitud: ' + seleccion.tipo_nombre
                                        : 'Datos de recepción'}</CardTitle>
                                    <CardDescription>{esDocumentoInstitucional
                                        ? 'Registra el asunto, los destinatarios y los datos de control del documento.'
                                        : seleccion?.tipo_documento === 'FUT'
                                        ? 'Transcribe los datos del FUT recibido. La fecha escrita en el FUT y la fecha de recepción en Mesa de Partes son distintas.'
                                        : seleccion
                                            ? 'Completa los datos del documento recibido para este tipo de trámite.'
                                            : 'Registra los datos del ingreso y del documento entregado físicamente.'}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    {seleccion ? (
                                        <>
                                            <input type="hidden" name="clasificacion" value={clasificacion} />
                                            <input type="hidden" name="tipo_documento" value={tipoDocumento} />
                                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 sm:col-span-2">
                                                <div>
                                                    <p className="text-sm font-medium">Formulario para: {seleccion.tipo_nombre}</p>
                                                    <p className="text-xs text-muted-foreground">{seleccion.requiere_solicitante ? `DNI ingresado: ${seleccion.dni}` : 'No requiere datos de solicitante ni DNI.'}</p>
                                                </div>
                                                <Button type="button" variant="outline" render={<Link href={TramiteController.create()} />}>{seleccion.requiere_solicitante ? 'Cambiar DNI o tipo' : 'Cambiar tipo'}</Button>
                                            </div>
                                        </>
                                    ) : (
                                        <FormSelect
                                            id="clasificacion"
                                            label="Clasificación"
                                            name="clasificacion"
                                            value={clasificacion}
                                            options={catalogos.clasificaciones}
                                            error={errors.clasificacion}
                                            onValueChange={changeClasificacion}
                                        />
                                    )}
                                    {clasificacion === 'estudiantil' && <>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="dni_estudiante">DNI del estudiante o egresado</Label>
                                            <Input
                                                id="dni_estudiante"
                                                value={dniEstudiante}
                                                onChange={(event) => changeDniEstudiante(event.target.value)}
                                                placeholder="Ingresa los 8 dígitos del DNI"
                                                maxLength={8}
                                                inputMode="numeric"
                                                pattern="[0-9]{8}"
                                                required
                                                readOnly={Boolean(seleccion)}
                                            />
                                            <p className="text-xs text-muted-foreground">El DNI vincula el trámite con la cuenta y completa los datos guardados en el perfil.{estudiantes.length === 0 ? ' No hay cuentas activas disponibles.' : ''}</p>
                                            <InputError message={errors.propietario_id ?? errors.persona_identificador} />
                                            {clasificacion === 'estudiantil' && <input type="hidden" name="propietario_id" value={estudianteSeleccionado?.id ?? ''} />}
                                            {clasificacion === 'estudiantil' && <input type="hidden" name="persona_nombre" value={estudianteSeleccionado?.name ?? ''} />}
                                            {clasificacion === 'estudiantil' && <input type="hidden" name="persona_identificador" value={estudianteSeleccionado?.dni ?? ''} />}
                                        </div>
                                        {estudianteSeleccionado ? (
                                            <div className="grid content-start gap-2 rounded-xl border p-4 text-sm">
                                                <p className="font-medium">Datos encontrados en el perfil</p>
                                                <p>{estudianteSeleccionado.name} · DNI {estudianteSeleccionado.dni}</p>
                                                <p className="text-muted-foreground">Correo institucional: {estudianteSeleccionado.email}</p>
                                                <p className="text-muted-foreground">Celular: {estudianteSeleccionado.celular || 'No registrado'}</p>
                                                {estudianteSeleccionado.correo_alternativo && <p className="text-muted-foreground">Correo alternativo: {estudianteSeleccionado.correo_alternativo}</p>}
                                            </div>
                                        ) : (
                                            <p className="self-center text-sm text-muted-foreground">{dniEstudiante.length === 8 ? 'No se encontró una cuenta activa con ese DNI. Registra primero al estudiante.' : 'Al ingresar un DNI registrado aparecerán los datos del perfil.'}</p>
                                        )}
                                    </>}
                                    {clasificacion === 'estudiantil' ? (
                                        <p className="self-center text-sm text-muted-foreground">
                                            El programa de estudios se toma del perfil del estudiante seleccionado.
                                        </p>
                                    ) : (
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="programa_estudio_id">Programa de estudios</Label>
                                            <select
                                                id="programa_estudio_id"
                                                name="programa_estudio_id"
                                                defaultValue={tramite?.programa_estudio_id ?? ''}
                                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="">No aplica</option>
                                                {programas.map((programa) => <option key={programa.id} value={programa.id}>{programa.nombre}</option>)}
                                            </select>
                                            <InputError message={errors.programa_estudio_id} />
                                        </div>
                                    )}
                                    {!seleccion && <FormSelect
                                        id="tipo_documento"
                                        label="Tipo de solicitud recibida"
                                        name="tipo_documento"
                                        value={tipoDocumento}
                                        options={catalogos.tipos_documento[clasificacion] ?? {}}
                                        description="Seleccione el tipo de solicitud recibida."
                                        error={errors.tipo_documento}
                                        onValueChange={(value) => changeTipoDocumento(value ?? '')}
                                    />}
                                    {!esDocumentoInstitucional ? <FormSelect
                                        id="formato_salida"
                                        label="Documento de respuesta previsto"
                                        name="formato_salida"
                                        value={formatoSalida}
                                        options={formatoObligatorio
                                            ? { [formatoObligatorio]: catalogos.formatos_salida[formatoObligatorio] ?? formatoObligatorio }
                                            : { pendiente: 'Pendiente de definir', ...catalogos.formatos_salida }}
                                        description={formatoObligatorio
                                            ? 'Este tipo de solicitud requiere el documento de respuesta indicado.'
                                            : 'Elija Constancia si esa será la respuesta; deje Pendiente de definir si aún no se ha decidido.'}
                                        error={errors.formato_salida}
                                        onValueChange={changeFormatoSalida}
                                    /> : <>
                                        <input type="hidden" name="formato_salida" value={formatoObligatorio ?? formatoSalida} />
                                        {modalidadSugerida && <input type="hidden" name="modalidad_documento" value={modalidadSugerida} />}
                                    </>}
                                    {!esDocumentoInstitucional && formatoSalida === 'memorando' && <FormSelect
                                        id="modalidad_documento"
                                        label="Modalidad del Memorando"
                                        name="modalidad_documento"
                                        value={modalidadDocumento}
                                        options={catalogos.modalidades_documento}
                                        error={errors.modalidad_documento}
                                        onValueChange={(value) => setModalidadDocumento(value ?? '')}
                                    />}
                                    <FormSelect
                                        id="destino_tipo"
                                        label="Tipo de destino"
                                        name="destino_tipo"
                                        value={destinoTipo}
                                        options={catalogos.destinos}
                                        error={errors.destino_tipo}
                                        onValueChange={changeDestinoTipo}
                                    />
                                    {destinoTipo === 'docente' ? (
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="buscar_docente">Buscar docente por nombre o DNI</Label>
                                            <Input
                                                id="buscar_docente"
                                                type="search"
                                                value={buscarDocente}
                                                onChange={(event) => setBuscarDocente(event.target.value)}
                                                placeholder="Escribe el nombre o DNI"
                                                maxLength={80}
                                            />
                                            <Label htmlFor="destino_docente_id">Docente de destino previsto</Label>
                                            <Select
                                                items={docentesFiltrados.map((docente) => ({ value: String(docente.id), label: `${docente.name} · DNI ${docente.dni ?? 'sin registrar'}` }))}
                                                name="destino_docente_id"
                                                value={destinoDocenteId}
                                                onValueChange={setDestinoDocenteId}
                                                required
                                            >
                                                <SelectTrigger id="destino_docente_id" className="w-full">
                                                    <SelectValue placeholder="Seleccione un docente activo" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {docentesFiltrados.length === 0
                                                            ? <SelectItem value="sin-resultados" disabled>No hay docentes con ese nombre o DNI.</SelectItem>
                                                            : docentesFiltrados.map((docente) => <SelectItem key={docente.id} value={String(docente.id)}>{docente.name} · DNI {docente.dni ?? 'sin registrar'}</SelectItem>)}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <p className="text-xs text-muted-foreground">La asignación formal del revisor se realiza después de preparar el borrador.{docentes.length === 0 ? ' No hay docentes activos disponibles.' : ''}</p>
                                            <InputError message={errors.destino_docente_id} />
                                        </div>
                                    ) : (
                                        <input
                                            type="hidden"
                                            name="destino_nombre"
                                            value={tramite?.destino_tipo === 'oficina' ? tramite.destino_nombre : ''}
                                        />
                                    )}
                                    <FormSelect
                                        id="prioridad"
                                        label="Prioridad"
                                        name="prioridad"
                                        value={prioridad}
                                        options={catalogos.prioridades}
                                        error={errors.prioridad}
                                        onValueChange={(value) => setPrioridad(value ?? 'normal')}
                                    />
                                    <Field id="fecha_llegada_oficina" label={esDocumentoInstitucional ? 'Fecha y hora de registro' : 'Fecha y hora de recepción en Mesa de Partes'} description={esDocumentoInstitucional ? 'Se propone la hora actual; puede corregirla si registra un documento anterior.' : 'Corresponde al ingreso físico del FUT. Se propone la hora actual; corríjala si registra una recepción anterior.'} error={errors.fecha_llegada_oficina}>
                                        <Input id="fecha_llegada_oficina" name="fecha_llegada_oficina" type="datetime-local" defaultValue={tramite?.fecha_llegada_oficina ?? ahora} required />
                                    </Field>
                                    <Field
                                        id="fecha_presentacion_original"
                                        label={seleccion?.tipo_documento === 'FUT' ? 'Fecha del documento (FUT)' : 'Fecha indicada en el documento (opcional)'}
                                        description={seleccion?.tipo_documento === 'FUT' ? 'Transcribe la fecha escrita junto a la firma en la parte inferior del FUT.' : 'Registra la fecha impresa o escrita en el documento recibido.'}
                                        error={errors.fecha_presentacion_original}
                                    >
                                        <Input id="fecha_presentacion_original" name="fecha_presentacion_original" type="date" defaultValue={tramite?.fecha_presentacion_original ?? ''} />
                                    </Field>
                                    <Field id="numero_expediente_externo" label="Referencia física externa" error={errors.numero_expediente_externo}>
                                        <Input id="numero_expediente_externo" name="numero_expediente_externo" defaultValue={tramite?.numero_expediente_externo ?? ''} maxLength={80} />
                                    </Field>
                                    <Field id="area_procedencia" label="Área de procedencia" error={errors.area_procedencia}>
                                        <Input id="area_procedencia" name="area_procedencia" defaultValue={tramite?.area_procedencia ?? ''} maxLength={160} />
                                    </Field>
                                    <Field id="persona_entrega_documento" label="Persona que entrega el documento" error={errors.persona_entrega_documento}>
                                        <Input id="persona_entrega_documento" name="persona_entrega_documento" defaultValue={tramite?.persona_entrega_documento ?? ''} maxLength={180} />
                                    </Field>
                                </CardContent>
                            </Card>

                            {clasificacion !== 'estudiantil' && !esDocumentoInstitucional && <Card>
                                <CardHeader>
                                    <CardTitle>Persona solicitante</CardTitle>
                                    <CardDescription>Datos de la persona que presenta la documentación.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <Field id="persona_nombre" label="Nombres y apellidos" error={errors.persona_nombre}>
                                        <Input id="persona_nombre" name="persona_nombre" defaultValue={tramite?.persona_nombre ?? ''} required maxLength={200} autoComplete="name" />
                                    </Field>
                                    <Field id="persona_identificador" label="DNI u otro documento de identidad" error={errors.persona_identificador}>
                                        <Input id="persona_identificador" name="persona_identificador" defaultValue={seleccion?.dni ?? tramite?.persona_identificador ?? ''} maxLength={50} readOnly={Boolean(seleccion)} />
                                    </Field>
                                </CardContent>
                            </Card>}

                            <Card>
                                <CardHeader>
                                    <CardTitle>{esDocumentoInstitucional ? 'Contenido del documento' : seleccion ? 'Solicitud: ' + seleccion.tipo_nombre : 'Contenido del trámite'}</CardTitle>
                                    <CardDescription>{esDocumentoInstitucional
                                        ? 'Registra el asunto y el contenido del informe o memorando.'
                                        : seleccion?.tipo_documento === 'FUT'
                                        ? 'Transcribe la sumilla y la fundamentación del pedido tal como aparecen en el FUT.'
                                        : 'Resume la solicitud recibida y registra debajo su detalle o fundamentación.'}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <Field id="asunto" label={esDocumentoInstitucional ? 'Asunto del documento' : seleccion?.tipo_documento === 'FUT' ? 'Resumen de la solicitud (sumilla)' : 'Resumen de la solicitud'} error={errors.asunto} className="sm:col-span-2">
                                        <Input id="asunto" name="asunto" defaultValue={tramite?.asunto ?? ''} required minLength={3} maxLength={255} placeholder={esDocumentoInstitucional ? 'Ej. Informe de actividades del área' : seleccion?.tipo_documento === 'FUT' ? 'Ej. Solicito prácticas pre profesionales' : 'Resume brevemente lo solicitado'} />
                                    </Field>
                                    <Field id="descripcion" label={esDocumentoInstitucional ? 'Contenido del documento' : seleccion?.tipo_documento === 'FUT' ? 'Fundamentación del pedido / detalle' : 'Detalle de la solicitud recibida'} error={errors.descripcion} className="sm:col-span-2">
                                        <textarea
                                            id="descripcion"
                                            name="descripcion"
                                            defaultValue={tramite?.descripcion ?? ''}
                                            rows={4}
                                            minLength={3}
                                            maxLength={5000}
                                            required
                                            className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                            placeholder={esDocumentoInstitucional ? 'Escribe el contenido principal del informe o memorando' : seleccion?.tipo_documento === 'FUT' ? 'Transcribe o resume la fundamentación del pedido del FUT' : 'Transcribe o resume el detalle del documento recibido'}
                                        />
                                    </Field>
                                    <Field id="observacion_recepcion" label="Observación de recepción" error={errors.observacion_recepcion} className="sm:col-span-2">
                                        <textarea
                                            id="observacion_recepcion"
                                            name="observacion_recepcion"
                                            defaultValue={tramite?.observacion_recepcion ?? ''}
                                            rows={3}
                                            maxLength={2000}
                                            className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                        />
                                    </Field>
                                    <Field id="folios" label="Cantidad de folios" error={errors.folios}>
                                        <Input id="folios" name="folios" type="number" defaultValue={tramite?.folios ?? ''} min={1} max={5000} inputMode="numeric" />
                                    </Field>
                                </CardContent>
                            </Card>

                            <ReceptionPreliminaryLists
                                initial={{
                                    personas_relacionadas: tramite?.personas_relacionadas ?? [],
                                    destinatarios: tramite?.destinatarios ?? [],
                                    personas_mencionadas: tramite?.personas_mencionadas ?? [],
                                }}
                                errors={errors}
                                tiposRelacion={catalogos.tipos_relacion}
                                cargos={cargos_institucionales}
                                requisitos={catalogos.requisitos_tipo[tipoDocumento] ?? { personas_relacionadas: false, destinatarios_multiples: false }}
                            />

                            {!tramite && <Card>
                                <CardHeader>
                                    <CardTitle>{esDocumentoInstitucional ? 'Archivos del documento' : 'Documentos recibidos'}</CardTitle>
                                    <CardDescription>Cada archivo es un documento independiente. Puede registrar varios en este trámite o agregarlos después.{catalogos.requisitos_tipo[tipoDocumento]?.documento_original ? ' Este tipo exige incluir un documento original.' : ''}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    {documentos.map((documento, index) => (
                                        <div key={documento.id} className="grid gap-4 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] sm:items-end">
                                            <FormSelect
                                                id={`documento-${documento.id}-categoria`}
                                                label="Categoría"
                                                name={`documentos[${index}][categoria]`}
                                                value={documento.categoria}
                                                options={{ documento_original: 'Documento original', documento_escaneado: 'Documento escaneado' }}
                                                error={errors[`documentos.${index}.categoria`]}
                                                onValueChange={(categoria) => setDocumentos((actuales) => actuales.map((actual) => actual.id === documento.id ? { ...actual, categoria: categoria ?? 'documento_original' } : actual))}
                                            />
                                            <Field id={`documento-${documento.id}-archivo`} label="PDF o imagen" error={errors[`documentos.${index}.archivo`]}>
                                                <Input
                                                    id={`documento-${documento.id}-archivo`}
                                                    name={`documentos[${index}][archivo]`}
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                    required
                                                />
                                            </Field>
                                            <Button type="button" variant="outline" size="icon" aria-label="Quitar documento" onClick={() => setDocumentos((actuales) => actuales.filter((actual) => actual.id !== documento.id))}>
                                                <X />
                                            </Button>
                                        </div>
                                    ))}
                                    <InputError message={errors.documentos} />
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button type="button" variant="outline" size="sm" onClick={() => {
                                            const id = siguienteDocumentoId.current++;
                                            setDocumentos((actuales) => [...actuales, { id, categoria: 'documento_original' }]);
                                        }}>
                                            <Plus data-icon="inline-start" /> Agregar documento
                                        </Button>
                                        <p className="text-xs text-muted-foreground">PDF, JPG o PNG; máximo 10 MB por archivo.</p>
                                    </div>
                                </CardContent>
                            </Card>}

                            {!tramite && <Card>
                                <CardHeader>
                                    <CardTitle>Revisión y confirmación</CardTitle>
                                    <CardDescription>{esDocumentoInstitucional ? 'Verifica los datos del documento antes de registrarlo.' : 'Verifica los datos de la recepción física antes de registrar el expediente.'}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-3">
                                        <Checkbox id="confirmar_recepcion" name="confirmar_recepcion" value="1" required aria-invalid={Boolean(errors.confirmar_recepcion)} />
                                        <Label htmlFor="confirmar_recepcion">{esDocumentoInstitucional ? 'Confirmo que revisé los datos del documento.' : 'Confirmo que revisé los datos de la recepción física.'}</Label>
                                    </div>
                                    <InputError message={errors.confirmar_recepcion} />
                                </CardContent>
                            </Card>}

                            <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                                <Button render={<Link href={tramite ? TramiteController.show({ tramite: tramite.id }) : TramiteController.index()} />} variant="outline">
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing || Boolean(seleccion && clasificacion === 'estudiantil' && !estudianteSeleccionado)}>
                                    {processing ? <Spinner /> : <FilePlus2 />}
                                    {tramite ? 'Guardar cambios' : esDocumentoInstitucional ? 'Registrar documento' : seleccion?.tipo_documento === 'FUT' ? 'Digitalizar FUT' : seleccion ? 'Digitalizar solicitud' : 'Guardar trámite'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

function Field({
    id,
    label,
    error,
    description,
    className = '',
    children,
}: {
    id: string;
    label: string;
    error?: string;
    description?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={`grid content-start gap-2 ${className}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {description && <p className="text-xs text-muted-foreground">{description}</p>}
            <InputError message={error} />
        </div>
    );
}

function FormSelect({
    id,
    label,
    name,
    value,
    options,
    description,
    error,
    onValueChange,
}: {
    id: string;
    label: string;
    name: string;
    value: string;
    options: Record<string, string>;
    description?: string;
    error?: string;
    onValueChange: (value: string | null) => void;
}) {
    const items = Object.entries(options).map(([optionValue, optionLabel]) => ({
        value: optionValue,
        label: optionLabel,
    }));

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select items={items} name={name} value={value} onValueChange={onValueChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {items.map((item) => (
                            <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            {description && <p className="text-xs text-muted-foreground">{description}</p>}
            <InputError message={error} />
        </div>
    );
}

TramiteCreate.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
    ],
};
