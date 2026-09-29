import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FilePlus2, Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
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
    destinos: Record<string, string>;
    prioridades: Record<string, string>;
};

type Props = {
    catalogos: Catalogos;
    ahora: string;
    estudiantes: Array<{ id: number; name: string }>;
    programas: Array<{ id: number; nombre: string }>;
    tramite: {
        id: number;
        codigo: string;
        clasificacion: string;
        tipo_documento: string;
        persona_nombre: string;
        persona_identificador: string | null;
        propietario_id: number | null;
        programa_estudio_id: number | null;
        destino_tipo: string;
        destino_nombre: string;
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
    } | null;
};

export default function TramiteCreate({ catalogos, ahora, estudiantes, programas, tramite }: Props) {
    const primeraClasificacion = tramite?.clasificacion ?? Object.keys(catalogos.clasificaciones)[0] ?? 'estudiantil';
    const [clasificacion, setClasificacion] = useState(primeraClasificacion);
    const [tipoDocumento, setTipoDocumento] = useState(
        tramite?.tipo_documento ?? Object.keys(catalogos.tipos_documento[primeraClasificacion] ?? {})[0] ?? '',
    );
    const [destinoTipo, setDestinoTipo] = useState(tramite?.destino_tipo ?? 'oficina');
    const [prioridad, setPrioridad] = useState(tramite?.prioridad ?? 'normal');
    const [propietarioId, setPropietarioId] = useState<string | null>(tramite?.propietario_id ? String(tramite.propietario_id) : null);
    const [documentos, setDocumentos] = useState<Array<{ id: number; categoria: string }>>([]);
    const siguienteDocumentoId = useRef(0);

    function changeClasificacion(value: string | null) {
        const nextClassification = value ?? primeraClasificacion;
        setClasificacion(nextClassification);
        setTipoDocumento(Object.keys(catalogos.tipos_documento[nextClassification] ?? {})[0] ?? '');
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
                        <p className="text-sm text-muted-foreground">Recepción y digitalización</p>
                        <h1 className="text-2xl font-semibold tracking-tight">{tramite ? `Editar ${tramite.codigo}` : 'Registrar trámite'}</h1>
                        <p className="text-sm text-muted-foreground">
                            {tramite ? 'Corrige los datos de recepción antes de la asignación. El código, el estado, los archivos y el historial se conservan.' : 'Registra los datos de ingreso y los documentos entregados físicamente.'}
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
                                    <CardTitle>Datos de recepción</CardTitle>
                                    <CardDescription>Clasifica el ingreso y define a quién se dirige.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <FormSelect
                                        id="clasificacion"
                                        label="Clasificación"
                                        name="clasificacion"
                                        value={clasificacion}
                                        options={catalogos.clasificaciones}
                                        error={errors.clasificacion}
                                        onValueChange={changeClasificacion}
                                    />
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="propietario_id">Estudiante o egresado relacionado</Label>
                                        <Select
                                            items={estudiantes.map((estudiante) => ({ value: String(estudiante.id), label: estudiante.name }))}
                                            name="propietario_id"
                                            value={propietarioId}
                                            onValueChange={setPropietarioId}
                                            required={clasificacion === 'estudiantil'}
                                        >
                                            <SelectTrigger id="propietario_id" className="w-full">
                                                <SelectValue placeholder="Selecciona una cuenta" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {estudiantes.map((estudiante) => <SelectItem key={estudiante.id} value={String(estudiante.id)}>{estudiante.name}</SelectItem>)}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">Obligatorio para los trámites de clasificación estudiantil.{estudiantes.length === 0 ? ' No hay cuentas activas disponibles.' : ''}</p>
                                        <InputError message={errors.propietario_id} />
                                    </div>
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
                                    <FormSelect
                                        id="tipo_documento"
                                        label="Tipo de documento"
                                        name="tipo_documento"
                                        value={tipoDocumento}
                                        options={catalogos.tipos_documento[clasificacion] ?? {}}
                                        error={errors.tipo_documento}
                                        onValueChange={(value) => setTipoDocumento(value ?? '')}
                                    />
                                    <FormSelect
                                        id="destino_tipo"
                                        label="Tipo de destino"
                                        name="destino_tipo"
                                        value={destinoTipo}
                                        options={catalogos.destinos}
                                        error={errors.destino_tipo}
                                        onValueChange={(value) => setDestinoTipo(value ?? 'oficina')}
                                    />
                                    <Field id="destino_nombre" label={destinoTipo === 'docente' ? 'Nombre del docente' : 'Oficina de destino'} error={errors.destino_nombre}>
                                        <Input id="destino_nombre" name="destino_nombre" defaultValue={tramite?.destino_nombre ?? ''} required maxLength={200} placeholder={destinoTipo === 'docente' ? 'Nombre y apellidos' : 'Ej. Secretaría Académica'} />
                                    </Field>
                                    <FormSelect
                                        id="prioridad"
                                        label="Prioridad"
                                        name="prioridad"
                                        value={prioridad}
                                        options={catalogos.prioridades}
                                        error={errors.prioridad}
                                        onValueChange={(value) => setPrioridad(value ?? 'normal')}
                                    />
                                    <Field id="fecha_llegada_oficina" label="Fecha y hora de llegada a oficina" error={errors.fecha_llegada_oficina}>
                                        <Input id="fecha_llegada_oficina" name="fecha_llegada_oficina" type="datetime-local" defaultValue={tramite?.fecha_llegada_oficina ?? ahora} required />
                                    </Field>
                                    <Field id="fecha_presentacion_original" label="Fecha de presentación original" error={errors.fecha_presentacion_original}>
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

                            <Card>
                                <CardHeader>
                                    <CardTitle>Persona solicitante</CardTitle>
                                    <CardDescription>Datos de la persona que presenta la documentación.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <Field id="persona_nombre" label="Nombres y apellidos" error={errors.persona_nombre}>
                                        <Input id="persona_nombre" name="persona_nombre" defaultValue={tramite?.persona_nombre ?? ''} required maxLength={200} autoComplete="name" />
                                    </Field>
                                    <Field id="persona_identificador" label="DNI o código de estudiante" error={errors.persona_identificador}>
                                        <Input id="persona_identificador" name="persona_identificador" defaultValue={tramite?.persona_identificador ?? ''} maxLength={50} />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Contenido del trámite</CardTitle>
                                    <CardDescription>El asunto facilita la búsqueda y el seguimiento del expediente.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <Field id="asunto" label="Asunto" error={errors.asunto} className="sm:col-span-2">
                                        <Input id="asunto" name="asunto" defaultValue={tramite?.asunto ?? ''} required minLength={3} maxLength={255} placeholder="Describe brevemente el motivo" />
                                    </Field>
                                    <Field id="descripcion" label="Descripción" error={errors.descripcion} className="sm:col-span-2">
                                        <textarea
                                            id="descripcion"
                                            name="descripcion"
                                            defaultValue={tramite?.descripcion ?? ''}
                                            rows={4}
                                            minLength={3}
                                            maxLength={5000}
                                            required
                                            className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                            placeholder="Descripción del trámite recibido"
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

                            {!tramite && <Card>
                                <CardHeader>
                                    <CardTitle>Documentos recibidos</CardTitle>
                                    <CardDescription>Cada archivo es un documento independiente. Puede registrar varios en esta recepción o agregarlos después.</CardDescription>
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
                                    <CardDescription>Verifica los datos de la recepción física antes de registrar el expediente.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-3">
                                        <Checkbox id="confirmar_recepcion" name="confirmar_recepcion" value="1" required aria-invalid={Boolean(errors.confirmar_recepcion)} />
                                        <Label htmlFor="confirmar_recepcion">Confirmo que revisé los datos de la recepción física.</Label>
                                    </div>
                                    <InputError message={errors.confirmar_recepcion} />
                                </CardContent>
                            </Card>}

                            <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                                <Button render={<Link href={tramite ? TramiteController.show({ tramite: tramite.id }) : TramiteController.index()} />} variant="outline">
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <FilePlus2 />}
                                    {tramite ? 'Guardar cambios' : 'Guardar trámite'}
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
    className = '',
    children,
}: {
    id: string;
    label: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={`grid content-start gap-2 ${className}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
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
    error,
    onValueChange,
}: {
    id: string;
    label: string;
    name: string;
    value: string;
    options: Record<string, string>;
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
            <InputError message={error} />
        </div>
    );
}

TramiteCreate.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
    ],
};
