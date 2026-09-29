import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    Save,
} from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/templates';
import { move, update } from '@/routes/admin/templates/fields';

type Campo = {
    id: number;
    clave_variable: string;
    etiqueta: string;
    grupo: string;
    tipo_campo: string;
    obligatorio: boolean | number;
    requiere_confirmacion: boolean | number;
    permite_html: boolean | number;
    orden: number;
    longitud_maxima: number | null;
    texto_ayuda: string | null;
    activo: boolean | number;
};

type Props = {
    plantilla: {
        id: number;
        codigo: string;
        version: number;
        nombre: string;
        estado: string;
    };
    usada: boolean;
    campos: Campo[];
};

const tipos = [
    'texto_corto',
    'texto_largo',
    'fecha',
    'numero',
    'select',
    'usuario',
    'cargo_institucional',
    'programa_estudios',
    'lista_destinatarios',
    'lista_personas',
    'texto_enriquecido',
];

const banderas = [
    { name: 'obligatorio', label: 'Obligatorio' },
    { name: 'requiere_confirmacion', label: 'Confirmación' },
    { name: 'permite_html', label: 'HTML limitado' },
    { name: 'activo', label: 'Activo' },
] as const;

export default function PlantillaCampos({ plantilla, usada, campos }: Props) {
    return (
        <>
            <Head title={`Campos · ${plantilla.nombre}`} />
            <div className="flex flex-col gap-6">
                {/* Header con navegación de retorno */}
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex flex-col gap-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                Campos: {plantilla.nombre}
                            </h1>
                            <Badge variant="outline" className="font-mono text-xs font-semibold">
                                v{plantilla.version}
                            </Badge>
                            <Badge variant="secondary" className="text-xs">
                                {plantilla.estado}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Código: <code className="font-mono font-semibold text-foreground">{plantilla.codigo}</code> · {campos.length} campos configurados
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        render={<Link href={index()} />}
                    >
                        <ArrowLeft data-icon="inline-start" />
                        Volver a plantillas
                    </Button>
                </header>

                {/* Aviso informativo si la versión ya fue utilizada */}
                {usada && (
                    <Card className="border-amber-500/30 bg-amber-500/5">
                        <CardHeader className="flex flex-row items-center gap-3 py-3">
                            <AlertCircle className="size-5 shrink-0 text-amber-500" />
                            <div>
                                <CardTitle className="text-sm font-semibold text-amber-500">
                                    Versión bloqueada para edición
                                </CardTitle>
                                <CardDescription className="text-xs text-muted-foreground">
                                    Esta versión ya ha sido utilizada en trámites registrados. Para modificar variables o campos, cree una nueva versión desde la lista de plantillas.
                                </CardDescription>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                {/* Listado de Campos */}
                <div className="flex flex-col gap-4">
                    {campos.map((campo, index) => (
                        <Card key={campo.id} className="transition-all hover:border-foreground/20">
                            <CardHeader className="pb-3">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline" className="font-mono text-xs font-semibold">
                                            #{campo.orden}
                                        </Badge>
                                        <CardTitle className="text-base font-semibold text-foreground">
                                            {campo.etiqueta}
                                        </CardTitle>
                                        <code className="text-xs font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                            {campo.clave_variable}
                                        </code>
                                        {campo.activo ? (
                                            <Badge variant="default" className="text-xs">
                                                <Check data-icon="inline-start" />
                                                Activo
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" className="text-xs">
                                                Inactivo
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Controles de orden */}
                                    {!usada && (
                                        <div className="flex items-center gap-1.5 self-end sm:self-center">
                                            <Form
                                                {...move.form({
                                                    plantilla: plantilla.id,
                                                    field: campo.id,
                                                })}
                                                disableWhileProcessing
                                            >
                                                <input type="hidden" name="direccion" value="up" />
                                                <Button
                                                    type="submit"
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    disabled={index === 0}
                                                    title="Mover arriba"
                                                >
                                                    <ArrowUp className="size-4" />
                                                    <span className="sr-only">Subir orden</span>
                                                </Button>
                                            </Form>
                                            <Form
                                                {...move.form({
                                                    plantilla: plantilla.id,
                                                    field: campo.id,
                                                })}
                                                disableWhileProcessing
                                            >
                                                <input type="hidden" name="direccion" value="down" />
                                                <Button
                                                    type="submit"
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    disabled={index === campos.length - 1}
                                                    title="Mover abajo"
                                                >
                                                    <ArrowDown className="size-4" />
                                                    <span className="sr-only">Bajar orden</span>
                                                </Button>
                                            </Form>
                                        </div>
                                    )}
                                </div>
                            </CardHeader>

                            <Form
                                {...update.form({
                                    plantilla: plantilla.id,
                                    field: campo.id,
                                })}
                                disableWhileProcessing
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <CardContent className="flex flex-col gap-4">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`etiqueta-${campo.id}`}>
                                                        Etiqueta visible
                                                    </Label>
                                                    <Input
                                                        id={`etiqueta-${campo.id}`}
                                                        name="etiqueta"
                                                        defaultValue={campo.etiqueta}
                                                        minLength={2}
                                                        maxLength={160}
                                                        required
                                                        disabled={usada}
                                                    />
                                                    <InputError message={errors.etiqueta} />
                                                </div>

                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`grupo-${campo.id}`}>
                                                        Grupo / Sección
                                                    </Label>
                                                    <Input
                                                        id={`grupo-${campo.id}`}
                                                        name="grupo"
                                                        defaultValue={campo.grupo}
                                                        maxLength={80}
                                                        required
                                                        disabled={usada}
                                                    />
                                                    <InputError message={errors.grupo} />
                                                </div>

                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`tipo-${campo.id}`}>
                                                        Tipo de dato
                                                    </Label>
                                                    <Select
                                                        name="tipo_campo"
                                                        defaultValue={campo.tipo_campo}
                                                        items={tipos.map((tipo) => ({
                                                            value: tipo,
                                                            label: tipo.replaceAll('_', ' '),
                                                        }))}
                                                        required
                                                        disabled={usada}
                                                    >
                                                        <SelectTrigger id={`tipo-${campo.id}`} className="w-full">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {tipos.map((tipo) => (
                                                                    <SelectItem key={tipo} value={tipo}>
                                                                        {tipo.replaceAll('_', ' ')}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError message={errors.tipo_campo} />
                                                </div>

                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`maximo-${campo.id}`}>
                                                        Longitud máxima
                                                    </Label>
                                                    <Input
                                                        id={`maximo-${campo.id}`}
                                                        name="longitud_maxima"
                                                        type="number"
                                                        min={1}
                                                        max={60000}
                                                        defaultValue={campo.longitud_maxima ?? ''}
                                                        placeholder="Sin límite"
                                                        disabled={usada}
                                                    />
                                                    <InputError message={errors.longitud_maxima} />
                                                </div>

                                                <div className="flex flex-col gap-2 sm:col-span-2">
                                                    <Label htmlFor={`ayuda-${campo.id}`}>
                                                        Texto de ayuda (tooltip / placeholder)
                                                    </Label>
                                                    <Input
                                                        id={`ayuda-${campo.id}`}
                                                        name="texto_ayuda"
                                                        defaultValue={campo.texto_ayuda ?? ''}
                                                        maxLength={255}
                                                        placeholder="Indicación para quien llena este campo..."
                                                        disabled={usada}
                                                    />
                                                    <InputError message={errors.texto_ayuda} />
                                                </div>
                                            </div>

                                            {/* Opciones booleanas en cuadrícula de 4 */}
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-border/40">
                                                {banderas.map(({ name, label }) => (
                                                    <div key={name} className="flex flex-col gap-1.5">
                                                        <Label htmlFor={`${name}-${campo.id}`} className="text-xs">
                                                            {label}
                                                        </Label>
                                                        <Select
                                                            name={name}
                                                            defaultValue={campo[name] ? '1' : '0'}
                                                            items={[
                                                                { value: '1', label: 'Sí' },
                                                                { value: '0', label: 'No' },
                                                            ]}
                                                            required
                                                            disabled={usada}
                                                        >
                                                            <SelectTrigger id={`${name}-${campo.id}`} className="w-full">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="1">Sí</SelectItem>
                                                                    <SelectItem value="0">No</SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                        <InputError message={errors[name]} />
                                                    </div>
                                                ))}
                                            </div>
                                        </CardContent>

                                        {!usada && (
                                            <CardFooter className="flex justify-end gap-2 border-t border-border/50 pt-3">
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    <Save data-icon="inline-start" />
                                                    Guardar cambios
                                                </Button>
                                            </CardFooter>
                                        )}
                                    </>
                                )}
                            </Form>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
