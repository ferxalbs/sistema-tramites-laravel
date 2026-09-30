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
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
                            Cada versión conserva su contenido. Publicar una versión la ofrece para nuevos borradores y desactiva las anteriores.
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
                <section className="flex flex-col gap-4" aria-label="Listado de plantillas">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold text-foreground">
                            Plantillas registradas ({plantillas.length})
                        </h2>
                    </div>

                    {plantillas.length === 0 ? (
                        <Card className="flex flex-col items-center justify-center p-8 text-center">
                            <FileText className="size-10 text-muted-foreground mb-3" />
                            <h3 className="font-semibold text-base text-foreground">
                                No hay plantillas registradas
                            </h3>
                            <p className="text-sm text-muted-foreground max-w-sm mt-1 mb-4">
                                Cree su primera plantilla para comenzar a generar documentos en los trámites.
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
                            Se guarda inactiva inicialmente para revisar sus variables y campos antes de publicarla.
                        </CardDescription>
                    </div>
                    <Badge variant="outline">Borrador</Badge>
                </div>
            </CardHeader>
            <Form {...store.form()} disableWhileProcessing>
                {({ errors, processing }) => (
                    <>
                        <CardContent className="flex flex-col gap-5">
                            {/* Grid 2 Columnas para metadatos */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="codigo-nuevo">Código único</Label>
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
                                    <InputError message={errors.codigo} />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="nombre-nuevo">Nombre de la plantilla</Label>
                                    <Input
                                        id="nombre-nuevo"
                                        name="nombre"
                                        placeholder="Informe de Conformidad de Trámite"
                                        minLength={3}
                                        maxLength={160}
                                        required
                                    />
                                    <InputError message={errors.nombre} />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="formato-nuevo">Formato de salida</Label>
                                    <Select
                                        name="tipo_documento_salida"
                                        items={formatos.map((item) => ({
                                            value: item.codigo,
                                            label: item.nombre,
                                        }))}
                                        required
                                    >
                                        <SelectTrigger id="formato-nuevo" className="w-full">
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
                                    <InputError
                                        message={errors.tipo_documento_salida}
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="modalidad-nueva">Modalidad</Label>
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
                                        <SelectTrigger id="modalidad-nueva" className="w-full">
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
                                    <InputError message={errors.modalidad} />
                                </div>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="descripcion-nueva">Descripción (opcional)</Label>
                                <Input
                                    id="descripcion-nueva"
                                    name="descripcion"
                                    placeholder="Indique brevemente el propósito de esta plantilla..."
                                    maxLength={255}
                                />
                                <InputError message={errors.descripcion} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap items-center justify-between gap-1">
                                    <Label htmlFor="contenido-nuevo">Contenido estructurado</Label>
                                    <span className="text-xs text-muted-foreground font-mono">
                                        Variables: {'{{NUMERO_DOCUMENTO_PREVIO}}'}, {'{{CONTENIDO_PRINCIPAL}}'}
                                    </span>
                                </div>
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
                                <InputError message={errors.contenido} />
                            </div>
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

    return (
        <Card className="transition-all hover:border-foreground/20">
            <CardHeader className="pb-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <CardTitle className="text-base font-semibold text-foreground">
                            {plantilla.nombre}
                        </CardTitle>
                        <Badge variant="outline" className="font-mono text-xs font-semibold">
                            v{plantilla.version}
                        </Badge>
                        {plantilla.activa ? (
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
                    Formato: <strong className="text-foreground">{plantilla.tipo_documento_salida}</strong>
                    {plantilla.modalidad && (
                        <span> · Modalidad: <strong className="text-foreground">{plantilla.modalidad}</strong></span>
                    )}
                    <span> · Estado: {plantilla.estado}</span>
                </CardDescription>
            </CardHeader>

            {plantilla.descripcion && (
                <CardContent className="pb-3 pt-0">
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

                <Collapsible open={isVersionOpen} onOpenChange={setIsVersionOpen}>
                    <CollapsibleTrigger
                        render={
                            <Button size="sm" variant="ghost">
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
            <Collapsible open={isVersionOpen} onOpenChange={setIsVersionOpen}>
                <CollapsibleContent>
                    <div className="border-t border-border/60 bg-muted/20 p-4 sm:p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">
                                    Generar versión {plantilla.version + 1}
                                </h4>
                                <p className="text-xs text-muted-foreground">
                                    Crea una copia editable de esta plantilla para modificar su estructura o contenido.
                                </p>
                            </div>
                        </div>

                        <Form
                            {...version.form({ plantilla: plantilla.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <div className="flex flex-col gap-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor={`nombre-${plantilla.id}`}>
                                                Nombre de la nueva versión
                                            </Label>
                                            <Input
                                                id={`nombre-${plantilla.id}`}
                                                name="nombre"
                                                defaultValue={plantilla.nombre}
                                                minLength={3}
                                                maxLength={160}
                                                required
                                            />
                                            <InputError message={errors.nombre} />
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor={`publicar-${plantilla.id}`}>
                                                Estado inicial de publicación
                                            </Label>
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
                                                <SelectTrigger id={`publicar-${plantilla.id}`} className="w-full">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="0">
                                                            Guardar borrador (inactiva)
                                                        </SelectItem>
                                                        <SelectItem value="1">
                                                            Publicar y activar inmediatamente
                                                        </SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.publicar} />
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor={`descripcion-${plantilla.id}`}>
                                            Descripción o notas de cambio
                                        </Label>
                                        <Input
                                            id={`descripcion-${plantilla.id}`}
                                            name="descripcion"
                                            defaultValue={plantilla.descripcion ?? ''}
                                            maxLength={255}
                                            placeholder="Detalle los cambios respecto a la versión anterior..."
                                        />
                                        <InputError message={errors.descripcion} />
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor={`contenido-${plantilla.id}`}>
                                                Contenido HTML de la nueva versión
                                            </Label>
                                            <span className="text-xs text-muted-foreground font-mono">
                                                Variables: {'{{NUMERO_DOCUMENTO_PREVIO}}'}, {'{{CONTENIDO_PRINCIPAL}}'}
                                            </span>
                                        </div>
                                        <Textarea
                                            id={`contenido-${plantilla.id}`}
                                            name="contenido"
                                            defaultValue={plantilla.contenido}
                                            maxLength={60000}
                                            rows={8}
                                            required
                                        />
                                        <InputError message={errors.contenido} />
                                    </div>

                                    <div className="flex items-center justify-end gap-2 pt-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setIsVersionOpen(false)}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <History data-icon="inline-start" />
                                            Crear versión {plantilla.version + 1}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}
