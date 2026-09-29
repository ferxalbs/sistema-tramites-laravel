import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
    { name: 'requiere_confirmacion', label: 'Requiere confirmación' },
    { name: 'permite_html', label: 'Permite HTML limitado' },
    { name: 'activo', label: 'Activo' },
] as const;

export default function PlantillaCampos({ plantilla, usada, campos }: Props) {
    return (
        <>
            <Head title={`Campos · ${plantilla.nombre}`} />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Campos de {plantilla.nombre} · v{plantilla.version}
                        </CardTitle>
                        <CardDescription>
                            {plantilla.codigo} · {plantilla.estado}.{' '}
                            {usada
                                ? 'Esta versión ya se utilizó; crea una nueva versión para cambiar sus campos.'
                                : 'Los cambios afectan solo esta versión.'}
                        </CardDescription>
                    </CardHeader>
                    <CardFooter>
                        <Button
                            render={<Link href={index()} />}
                            variant="outline"
                        >
                            Volver a plantillas
                        </Button>
                    </CardFooter>
                </Card>
                {campos.map((campo) => (
                    <Card key={campo.id}>
                        <CardHeader>
                            <CardTitle>{campo.etiqueta}</CardTitle>
                            <CardDescription>
                                {campo.clave_variable} · orden {campo.orden} ·{' '}
                                {campo.activo ? 'activo' : 'inactivo'}
                            </CardDescription>
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
                                    <CardContent className="grid gap-4">
                                        <Label htmlFor={`etiqueta-${campo.id}`}>
                                            Etiqueta
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
                                        <Label htmlFor={`grupo-${campo.id}`}>
                                            Grupo
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
                                        <Label htmlFor={`tipo-${campo.id}`}>
                                            Tipo de campo
                                        </Label>
                                        <Select
                                            name="tipo_campo"
                                            defaultValue={campo.tipo_campo}
                                            items={tipos.map((tipo) => ({
                                                value: tipo,
                                                label: tipo.replaceAll(
                                                    '_',
                                                    ' ',
                                                ),
                                            }))}
                                            required
                                            disabled={usada}
                                        >
                                            <SelectTrigger
                                                id={`tipo-${campo.id}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {tipos.map((tipo) => (
                                                        <SelectItem
                                                            key={tipo}
                                                            value={tipo}
                                                        >
                                                            {tipo.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.tipo_campo}
                                        />
                                        <Label htmlFor={`maximo-${campo.id}`}>
                                            Longitud máxima
                                        </Label>
                                        <Input
                                            id={`maximo-${campo.id}`}
                                            name="longitud_maxima"
                                            type="number"
                                            min={1}
                                            max={60000}
                                            defaultValue={
                                                campo.longitud_maxima ?? ''
                                            }
                                            disabled={usada}
                                        />
                                        <InputError
                                            message={errors.longitud_maxima}
                                        />
                                        <Label htmlFor={`ayuda-${campo.id}`}>
                                            Texto de ayuda
                                        </Label>
                                        <Input
                                            id={`ayuda-${campo.id}`}
                                            name="texto_ayuda"
                                            defaultValue={
                                                campo.texto_ayuda ?? ''
                                            }
                                            maxLength={255}
                                            disabled={usada}
                                        />
                                        <InputError
                                            message={errors.texto_ayuda}
                                        />
                                        {banderas.map(({ name, label }) => (
                                            <div
                                                key={name}
                                                className="grid gap-2"
                                            >
                                                <Label
                                                    htmlFor={`${name}-${campo.id}`}
                                                >
                                                    {label}
                                                </Label>
                                                <Select
                                                    name={name}
                                                    defaultValue={
                                                        campo[name] ? '1' : '0'
                                                    }
                                                    items={[
                                                        {
                                                            value: '1',
                                                            label: 'Sí',
                                                        },
                                                        {
                                                            value: '0',
                                                            label: 'No',
                                                        },
                                                    ]}
                                                    required
                                                    disabled={usada}
                                                >
                                                    <SelectTrigger
                                                        id={`${name}-${campo.id}`}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            <SelectItem value="1">
                                                                Sí
                                                            </SelectItem>
                                                            <SelectItem value="0">
                                                                No
                                                            </SelectItem>
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors[name]}
                                                />
                                            </div>
                                        ))}
                                    </CardContent>
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={usada || processing}
                                        >
                                            Guardar campo
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                        <CardFooter>
                            {(['up', 'down'] as const).map((direccion) => (
                                <Form
                                    key={direccion}
                                    {...move.form({
                                        plantilla: plantilla.id,
                                        field: campo.id,
                                    })}
                                    disableWhileProcessing
                                >
                                    <input
                                        type="hidden"
                                        name="direccion"
                                        value={direccion}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={usada}
                                    >
                                        {direccion === 'up' ? 'Subir' : 'Bajar'}
                                    </Button>
                                </Form>
                            ))}
                        </CardFooter>
                    </Card>
                ))}
            </main>
        </>
    );
}
