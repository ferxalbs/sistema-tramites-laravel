import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FilePlus2 } from 'lucide-react';
import { useState } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    hoy: string;
    estudiantes: Array<{ id: number; name: string }>;
};

export default function TramiteCreate({ catalogos, hoy, estudiantes }: Props) {
    const primeraClasificacion = Object.keys(catalogos.clasificaciones)[0] ?? 'estudiantil';
    const [clasificacion, setClasificacion] = useState(primeraClasificacion);
    const [tipoDocumento, setTipoDocumento] = useState(
        Object.keys(catalogos.tipos_documento[primeraClasificacion] ?? {})[0] ?? '',
    );
    const [destinoTipo, setDestinoTipo] = useState('oficina');
    const [prioridad, setPrioridad] = useState('normal');
    const [propietarioId, setPropietarioId] = useState<string | null>(null);

    function changeClasificacion(value: string | null) {
        const nextClassification = value ?? primeraClasificacion;
        setClasificacion(nextClassification);
        setTipoDocumento(Object.keys(catalogos.tipos_documento[nextClassification] ?? {})[0] ?? '');
    }

    return (
        <>
            <Head title="Registrar trámite" />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button render={<Link href={TramiteController.index()} />} variant="outline" size="icon" aria-label="Volver a la bandeja">
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">Recepción y digitalización</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Registrar trámite</h1>
                        <p className="text-sm text-muted-foreground">
                            Registra los datos de ingreso. Puedes adjuntar el documento digitalizado ahora o después.
                        </p>
                    </div>
                </header>

                <Form
                    action={TramiteController.store.url()}
                    method="post"
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
                                        <Input id="destino_nombre" name="destino_nombre" required maxLength={200} placeholder={destinoTipo === 'docente' ? 'Nombre y apellidos' : 'Ej. Secretaría Académica'} />
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
                                    <Field id="fecha_recepcion" label="Fecha de recepción" error={errors.fecha_recepcion}>
                                        <Input id="fecha_recepcion" name="fecha_recepcion" type="date" defaultValue={hoy} required />
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
                                        <Input id="persona_nombre" name="persona_nombre" required maxLength={200} autoComplete="name" />
                                    </Field>
                                    <Field id="persona_identificador" label="DNI o código de estudiante" error={errors.persona_identificador}>
                                        <Input id="persona_identificador" name="persona_identificador" maxLength={50} />
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
                                        <Input id="asunto" name="asunto" required maxLength={200} placeholder="Describe brevemente el motivo" />
                                    </Field>
                                    <Field id="descripcion" label="Descripción u observación" error={errors.descripcion} className="sm:col-span-2">
                                        <textarea
                                            id="descripcion"
                                            name="descripcion"
                                            rows={4}
                                            maxLength={10000}
                                            className="w-full resize-y rounded-2xl border border-transparent bg-input/50 px-3 py-2 text-sm outline-none transition focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30"
                                            placeholder="Información adicional para el seguimiento"
                                        />
                                    </Field>
                                    <Field id="folios" label="Cantidad de folios" error={errors.folios}>
                                        <Input id="folios" name="folios" type="number" min={1} max={10000} inputMode="numeric" />
                                    </Field>
                                    <Field id="documento" label="Documento digitalizado (opcional)" error={errors.documento}>
                                        <Input
                                            id="documento"
                                            name="documento"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                        />
                                        <p className="text-xs text-muted-foreground">PDF, JPG o PNG; máximo 10 MB.</p>
                                    </Field>
                                </CardContent>
                            </Card>

                            <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                                <Button render={<Link href={TramiteController.index()} />} variant="outline">
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <FilePlus2 />}
                                    Guardar trámite
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
        { title: 'Registrar trámite', href: TramiteController.create() },
    ],
};
