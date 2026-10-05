import { Form, Head, Link } from '@inertiajs/react';
import {
    Ban,
    Check,
    ChevronDown,
    ChevronUp,
    FileText,
    History,
    Plus,
    Save,
    SlidersHorizontal,
} from 'lucide-react';
import {
    Children,
    cloneElement,
    isValidElement,
    useState,
    type ReactElement,
    type ReactNode,
} from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Field as ShadcnField,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fields, state, store, version } from '@/routes/admin/templates';

type Plantilla = {
    id: number;
    codigo: string;
    version: number;
    nombre: string;
    descripcion: string | null;
    tipo_documento_salida: string;
    modalidad: string | null;
    contenido: string;
    estado: string;
    activa: boolean;
};

type Props = {
    plantillas: Plantilla[];
    formatos: Array<{ codigo: string; nombre: string }>;
    modalidades: Array<{ codigo: string; nombre: string }>;
};

export default function Plantillas({
    plantillas,
    formatos,
    modalidades,
}: Props) {
    const [showNewForm, setShowNewForm] = useState(false);

    return (
        <>
            <Head title="Plantillas documentales" />
            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Plantillas documentales
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cada versión conserva su contenido. Publicar una
                            versión la ofrece para nuevos borradores y desactiva
                            las anteriores.
                        </p>
                    </div>
                    <Button
                        onClick={() => setShowNewForm(!showNewForm)}
                        variant={showNewForm ? 'outline' : 'default'}
                    >
                        <Plus data-icon="inline-start" />
                        {showNewForm ? 'Cerrar formulario' : 'Nueva plantilla'}
                    </Button>
                </header>

                {/* Formulario Nueva Plantilla (Colapsable o directo) */}
                {showNewForm && (
                    <NuevaPlantillaCard
                        formatos={formatos}
                        modalidades={modalidades}
                        onCancel={() => setShowNewForm(false)}
                    />
                )}

                {/* Listado de Plantillas */}
                <section
                    className="flex flex-col gap-4"
                    aria-label="Listado de plantillas"
                >
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold text-foreground">
                            Plantillas registradas ({plantillas.length})
                        </h2>
                    </div>

                    {plantillas.length === 0 ? (
                        <Card className="flex flex-col items-center justify-center p-8 text-center">
                            <FileText className="mb-3 size-10 text-muted-foreground" />
                            <h3 className="text-base font-semibold text-foreground">
                                No hay plantillas registradas
                            </h3>
                            <p className="mt-1 mb-4 max-w-sm text-sm text-muted-foreground">
                                Cree su primera plantilla para comenzar a
                                generar documentos en los trámites.
                            </p>
                            <Button onClick={() => setShowNewForm(true)}>
                                <Plus data-icon="inline-start" />
                                Crear plantilla
                            </Button>
                        </Card>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {plantillas.map((plantilla) => (
                                <PlantillaItem
                                    key={plantilla.id}
                                    plantilla={plantilla}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function NuevaPlantillaCard({
    formatos,
    modalidades,
    onCancel,
}: {
    formatos: Array<{ codigo: string; nombre: string }>;
    modalidades: Array<{ codigo: string; nombre: string }>;
    onCancel: () => void;
}) {
    return (
        <Card className="border-primary/20 shadow-sm">
            <CardHeader className="pb-4">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg font-semibold">
                            Crear nueva plantilla
                        </CardTitle>
                        <CardDescription>
                            Se guarda inactiva inicialmente para revisar sus
                            variables y campos antes de publicarla.
                        </CardDescription>
                    </div>
                    <Badge variant="outline">Borrador</Badge>
                </div>
            </CardHeader>
            <Form {...store.form()} disableWhileProcessing>
                {({ errors, processing }) => (
                    <>
                        <CardContent>
                            <FieldGroup className="gap-5">
                                {/* Grid 2 Columnas para metadatos */}
                                <FieldGroup className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <Field
                                        id="codigo-nuevo"
                                        label="Código único"
                                        error={errors.codigo}
                                    >
                                        <Input
                                            id="codigo-nuevo"
                                            name="codigo"
                                            placeholder="EJ: INF_CONFORMIDAD_01"
                                            pattern="[A-Z0-9_]+"
                                            minLength={3}
                                            maxLength={80}
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Mayúsculas, números y guiones bajos.
                                        </p>
                                    </Field>

                                    <Field
                                        id="nombre-nuevo"
                                        label="Nombre de la plantilla"
                                        error={errors.nombre}
                                    >
                                        <Input
                                            id="nombre-nuevo"
                                            name="nombre"
                                            placeholder="Informe de Conformidad de Trámite"
                                            minLength={3}
                                            maxLength={160}
                                            required
                                        />
                                    </Field>

                                    <ShadcnField
                                        data-invalid={
                                            errors.tipo_documento_salida
                                                ? true
                                                : undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="formato-nuevo">
                                            Formato de salida
                                        </FieldLabel>
                                        <Select
                                            name="tipo_documento_salida"
                                            items={formatos.map((item) => ({
                                                value: item.codigo,
                                                label: item.nombre,
                                            }))}
                                            required
                                        >
                                            <SelectTrigger
                                                id="formato-nuevo"
                                                className="w-full"
                                                aria-describedby={
                                                    errors.tipo_documento_salida
                                                        ? 'formato-nuevo-error'
                                                        : undefined
                                                }
                                                aria-invalid={
                                                    errors.tipo_documento_salida
                                                        ? true
                                                        : undefined
                                                }
                                            >
                                                <SelectValue placeholder="Selecciona formato" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {formatos.map((item) => (
                                                        <SelectItem
                                                            key={item.codigo}
                                                            value={item.codigo}
                                                        >
                                                            {item.nombre}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError id="formato-nuevo-error">
                                            {errors.tipo_documento_salida}
                                        </FieldError>
                                    </ShadcnField>

                                    <ShadcnField
                                        data-invalid={
                                            errors.modalidad ? true : undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="modalidad-nueva">
                                            Modalidad
                                        </FieldLabel>
                                        <Select
                                            name="modalidad"
                                            defaultValue="sin_modalidad"
                                            items={[
                                                {
                                                    value: 'sin_modalidad',
                                                    label: 'Sin modalidad',
                                                },
                                                ...modalidades.map((item) => ({
                                                    value: item.codigo,
                                                    label: item.nombre,
                                                })),
                                            ]}
                                        >
                                            <SelectTrigger
                                                id="modalidad-nueva"
                                                className="w-full"
                                                aria-describedby={
                                                    errors.modalidad
                                                        ? 'modalidad-nueva-error'
                                                        : undefined
                                                }
                                                aria-invalid={
                                                    errors.modalidad
                                                        ? true
                                                        : undefined
                                                }
                                            >
                                                <SelectValue placeholder="Sin modalidad" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="sin_modalidad">
                                                        Sin modalidad
                                                    </SelectItem>
                                                    {modalidades.map((item) => (
                                                        <SelectItem
                                                            key={item.codigo}
                                                            value={item.codigo}
                                                        >
                                                            {item.nombre}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError id="modalidad-nueva-error">
                                            {errors.modalidad}
                                        </FieldError>
                                    </ShadcnField>
                                </FieldGroup>

                                <Field
                                    id="descripcion-nueva"
                                    label="Descripción (opcional)"
                                    error={errors.descripcion}
                                >
                                    <Input
                                        id="descripcion-nueva"
                                        name="descripcion"
                                        placeholder="Indique brevemente el propósito de esta plantilla..."
                                        maxLength={255}
                                    />
                                </Field>

                                <Field
                                    id="contenido-nuevo"
                                    label={
                                        <>
                                            <span>Contenido estructurado</span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                Variables:{' '}
                                                {'{{NUMERO_DOCUMENTO_PREVIO}}'},{' '}
                                                {'{{CONTENIDO_PRINCIPAL}}'}
                                            </span>
                                        </>
                                    }
                                    error={errors.contenido}
                                >
                                    <Textarea
                                        id="contenido-nuevo"
                                        name="contenido"
                                        rows={6}
                                        maxLength={60000}
                                        required
                                        defaultValue={
                                            '<article><h1>{{NUMERO_DOCUMENTO_PREVIO}}</h1><p>{{CONTENIDO_PRINCIPAL}}</p></article>'
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                        <CardFooter className="flex items-center justify-end gap-3 border-t border-border/50 pt-4">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onCancel}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                <Save data-icon="inline-start" />
                                Guardar plantilla
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}

function PlantillaItem({ plantilla }: { plantilla: Plantilla }) {
    const [isVersionOpen, setIsVersionOpen] = useState(false);
    const esReferencial = [
        'JUSTIFICACION_TARDANZA_REFERENCIAL',
        'CONSTANCIA_PRACTICA_REFERENCIAL',
    ].includes(plantilla.codigo);

    return (
        <Card className="transition-all hover:border-foreground/20">
            <CardHeader className="pb-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <CardTitle className="text-base font-semibold text-foreground">
                            {plantilla.nombre}
                        </CardTitle>
                        <Badge
                            variant="outline"
                            className="font-mono text-xs font-semibold"
                        >
                            v{plantilla.version}
                        </Badge>
                        {esReferencial ? (
                            <Badge variant="secondary" className="text-xs">
                                Solo borrador
                            </Badge>
                        ) : plantilla.activa ? (
                            <Badge variant="default" className="text-xs">
                                <Check data-icon="inline-start" />
                                Activa
                            </Badge>
                        ) : (
                            <Badge variant="secondary" className="text-xs">
                                Inactiva
                            </Badge>
                        )}
                    </div>
                    <span className="font-mono text-xs text-muted-foreground">
                        {plantilla.codigo}
                    </span>
                </div>
                <CardDescription className="text-xs">
                    Formato:{' '}
                    <strong className="text-foreground">
                        {plantilla.tipo_documento_salida}
                    </strong>
                    {plantilla.modalidad && (
                        <span>
                            {' '}
                            · Modalidad:{' '}
                            <strong className="text-foreground">
                                {plantilla.modalidad}
                            </strong>
                        </span>
                    )}
                    <span> · Estado: {plantilla.estado}</span>
                </CardDescription>
            </CardHeader>

            {plantilla.descripcion && (
                <CardContent className="pt-0 pb-3">
                    <p className="text-sm text-muted-foreground">
                        {plantilla.descripcion}
                    </p>
                </CardContent>
            )}

            <CardFooter className="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        render={
                            <Link
                                href={fields({
                                    plantilla: plantilla.id,
                                })}
                            />
                        }
                    >
                        <SlidersHorizontal data-icon="inline-start" />
                        Configurar campos
                    </Button>

                    <Form
                        {...state.form({ plantilla: plantilla.id })}
                        disableWhileProcessing
                    >
                        <input
                            type="hidden"
                            name="activa"
                            value={plantilla.activa ? '0' : '1'}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={esReferencial}
                            variant={plantilla.activa ? 'ghost' : 'secondary'}
                        >
                            {plantilla.activa ? (
                                <>
                                    <Ban data-icon="inline-start" />
                                    Desactivar
                                </>
                            ) : (
                                <>
                                    <Check data-icon="inline-start" />
                                    Publicar esta versión
                                </>
                            )}
                        </Button>
                    </Form>
                </div>

                <Collapsible
                    open={esReferencial ? false : isVersionOpen}
                    onOpenChange={setIsVersionOpen}
                >
                    <CollapsibleTrigger
                        render={
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={esReferencial}
                            >
                                <History data-icon="inline-start" />
                                Nueva versión
                                {isVersionOpen ? (
                                    <ChevronUp data-icon="inline-end" />
                                ) : (
                                    <ChevronDown data-icon="inline-end" />
                                )}
                            </Button>
                        }
                    />
                </Collapsible>
            </CardFooter>

            {/* Sub-formulario desplegable para crear siguiente versión */}
            <Collapsible
                open={esReferencial ? false : isVersionOpen}
                onOpenChange={setIsVersionOpen}
            >
                <CollapsibleContent>
                    <div className="border-t border-border/60 bg-muted/20 p-4 sm:p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">
                                    Generar versión {plantilla.version + 1}
                                </h4>
                                <p className="text-xs text-muted-foreground">
                                    Crea una copia editable de esta plantilla
                                    para modificar su estructura o contenido.
                                </p>
                            </div>
                        </div>

                        <Form
                            {...version.form({ plantilla: plantilla.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <FieldGroup className="gap-4">
                                    <FieldGroup className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <Field
                                            id={`nombre-${plantilla.id}`}
                                            label="Nombre de la nueva versión"
                                            error={errors.nombre}
                                        >
                                            <Input
                                                id={`nombre-${plantilla.id}`}
                                                name="nombre"
                                                defaultValue={plantilla.nombre}
                                                minLength={3}
                                                maxLength={160}
                                                required
                                            />
                                        </Field>

                                        <ShadcnField
                                            data-invalid={
                                                errors.publicar
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <FieldLabel
                                                htmlFor={`publicar-${plantilla.id}`}
                                            >
                                                Estado inicial de publicación
                                            </FieldLabel>
                                            <Select
                                                name="publicar"
                                                defaultValue="0"
                                                items={[
                                                    {
                                                        value: '0',
                                                        label: 'Guardar borrador (inactiva)',
                                                    },
                                                    {
                                                        value: '1',
                                                        label: 'Publicar y activar inmediatamente',
                                                    },
                                                ]}
                                                required
                                            >
                                                <SelectTrigger
                                                    id={`publicar-${plantilla.id}`}
                                                    className="w-full"
                                                    aria-describedby={
                                                        errors.publicar
                                                            ? `publicar-${plantilla.id}-error`
                                                            : undefined
                                                    }
                                                    aria-invalid={
                                                        errors.publicar
                                                            ? true
                                                            : undefined
                                                    }
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="0">
                                                            Guardar borrador
                                                            (inactiva)
                                                        </SelectItem>
                                                        <SelectItem value="1">
                                                            Publicar y activar
                                                            inmediatamente
                                                        </SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError
                                                id={`publicar-${plantilla.id}-error`}
                                            >
                                                {errors.publicar}
                                            </FieldError>
                                        </ShadcnField>
                                    </FieldGroup>

                                    <Field
                                        id={`descripcion-${plantilla.id}`}
                                        label="Descripción o notas de cambio"
                                        error={errors.descripcion}
                                    >
                                        <Input
                                            id={`descripcion-${plantilla.id}`}
                                            name="descripcion"
                                            defaultValue={
                                                plantilla.descripcion ?? ''
                                            }
                                            maxLength={255}
                                            placeholder="Detalle los cambios respecto a la versión anterior..."
                                        />
                                    </Field>

                                    <Field
                                        id={`contenido-${plantilla.id}`}
                                        label={
                                            <>
                                                <span>
                                                    Contenido HTML de la nueva
                                                    versión
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    Variables:{' '}
                                                    {
                                                        '{{NUMERO_DOCUMENTO_PREVIO}}'
                                                    }
                                                    ,{' '}
                                                    {'{{CONTENIDO_PRINCIPAL}}'}
                                                </span>
                                            </>
                                        }
                                        error={errors.contenido}
                                    >
                                        <Textarea
                                            id={`contenido-${plantilla.id}`}
                                            name="contenido"
                                            defaultValue={plantilla.contenido}
                                            maxLength={60000}
                                            rows={8}
                                            required
                                        />
                                    </Field>

                                    <div className="flex items-center justify-end gap-2 pt-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setIsVersionOpen(false)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <History data-icon="inline-start" />
                                            Crear versión{' '}
                                            {plantilla.version + 1}
                                        </Button>
                                    </div>
                                </FieldGroup>
                            )}
                        </Form>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: ReactNode;
    error?: string;
    children: ReactNode;
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
        <ShadcnField
            className="gap-2"
            data-invalid={invalid ? true : undefined}
        >
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {control}
            <FieldError id={errorId}>{error}</FieldError>
        </ShadcnField>
    );
}
