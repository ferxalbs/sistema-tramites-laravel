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

export default function Plantillas({
    plantillas,
    formatos,
    modalidades,
}: {
    plantillas: Plantilla[];
    formatos: Array<{ codigo: string; nombre: string }>;
    modalidades: Array<{ codigo: string; nombre: string }>;
}) {
    return (
        <>
            <Head title="Plantillas documentales" />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>Plantillas documentales</CardTitle>
                        <CardDescription>
                            Cada versión conserva su contenido. Publicar una
                            versión la ofrece para nuevos borradores y desactiva
                            las anteriores del mismo código.
                        </CardDescription>
                    </CardHeader>
                </Card>
                <NuevaPlantillaForm
                    formatos={formatos}
                    modalidades={modalidades}
                />
                {plantillas.map((plantilla) => (
                    <Card key={plantilla.id}>
                        <CardHeader>
                            <CardTitle>
                                {plantilla.nombre} · v{plantilla.version}
                            </CardTitle>
                            <CardDescription>
                                {plantilla.codigo} ·{' '}
                                {plantilla.tipo_documento_salida}{' '}
                                {plantilla.modalidad ?? ''} · {plantilla.estado}
                                {plantilla.activa ? ' · activa' : ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                render={
                                    <Link
                                        href={fields({
                                            plantilla: plantilla.id,
                                        })}
                                    />
                                }
                                variant="outline"
                            >
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
                                <Button type="submit" variant="outline">
                                    {plantilla.activa
                                        ? 'Desactivar versión'
                                        : 'Publicar esta versión'}
                                </Button>
                            </Form>
                        </CardContent>
                        <Form
                            {...version.form({ plantilla: plantilla.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <Label
                                            htmlFor={`nombre-${plantilla.id}`}
                                        >
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
                                        <Label
                                            htmlFor={`descripcion-${plantilla.id}`}
                                        >
                                            Descripción
                                        </Label>
                                        <Input
                                            id={`descripcion-${plantilla.id}`}
                                            name="descripcion"
                                            defaultValue={
                                                plantilla.descripcion ?? ''
                                            }
                                            maxLength={255}
                                        />
                                        <InputError
                                            message={errors.descripcion}
                                        />
                                        <Label
                                            htmlFor={`contenido-${plantilla.id}`}
                                        >
                                            Contenido de la nueva versión
                                        </Label>
                                        <textarea
                                            id={`contenido-${plantilla.id}`}
                                            name="contenido"
                                            defaultValue={plantilla.contenido}
                                            maxLength={60000}
                                            rows={10}
                                            className="w-full rounded-md border border-input bg-background p-3"
                                            required
                                        />
                                        <CardDescription>
                                            Incluye{' '}
                                            {'{{NUMERO_DOCUMENTO_PREVIO}}'} y{' '}
                                            {'{{CONTENIDO_PRINCIPAL}}'}. El PDF
                                            actual todavía usa su composición
                                            fija.
                                        </CardDescription>
                                        <InputError
                                            message={errors.contenido}
                                        />
                                        <Label
                                            htmlFor={`publicar-${plantilla.id}`}
                                        >
                                            Estado inicial
                                        </Label>
                                        <Select
                                            name="publicar"
                                            defaultValue="0"
                                            items={[
                                                {
                                                    value: '0',
                                                    label: 'Guardar borrador',
                                                },
                                                {
                                                    value: '1',
                                                    label: 'Publicar y activar',
                                                },
                                            ]}
                                            required
                                        >
                                            <SelectTrigger
                                                id={`publicar-${plantilla.id}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="0">
                                                        Guardar borrador
                                                    </SelectItem>
                                                    <SelectItem value="1">
                                                        Publicar y activar
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.publicar} />
                                    </CardContent>
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Crear versión siguiente
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    </Card>
                ))}
            </main>
        </>
    );
}

function NuevaPlantillaForm({
    formatos,
    modalidades,
}: {
    formatos: Array<{ codigo: string; nombre: string }>;
    modalidades: Array<{ codigo: string; nombre: string }>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Nueva plantilla</CardTitle>
                <CardDescription>
                    Se guarda inactiva para revisar sus campos antes de
                    publicarla.
                </CardDescription>
            </CardHeader>
            <Form {...store.form()} disableWhileProcessing>
                {({ errors, processing }) => (
                    <>
                        <CardContent className="grid gap-4">
                            <Label htmlFor="codigo-nuevo">Código</Label>
                            <Input
                                id="codigo-nuevo"
                                name="codigo"
                                pattern="[A-Z0-9_]+"
                                minLength={3}
                                maxLength={80}
                                required
                            />
                            <InputError message={errors.codigo} />
                            <Label htmlFor="nombre-nuevo">Nombre</Label>
                            <Input
                                id="nombre-nuevo"
                                name="nombre"
                                minLength={3}
                                maxLength={160}
                                required
                            />
                            <InputError message={errors.nombre} />
                            <Label htmlFor="formato-nuevo">Formato</Label>
                            <Select
                                name="tipo_documento_salida"
                                items={formatos.map((item) => ({
                                    value: item.codigo,
                                    label: item.nombre,
                                }))}
                                required
                            >
                                <SelectTrigger id="formato-nuevo">
                                    <SelectValue placeholder="Selecciona el formato" />
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
                                <SelectTrigger id="modalidad-nueva">
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
                            <Label htmlFor="descripcion-nueva">
                                Descripción
                            </Label>
                            <Input
                                id="descripcion-nueva"
                                name="descripcion"
                                maxLength={255}
                            />
                            <InputError message={errors.descripcion} />
                            <Label htmlFor="contenido-nuevo">
                                Contenido estructurado
                            </Label>
                            <textarea
                                id="contenido-nuevo"
                                name="contenido"
                                rows={8}
                                maxLength={60000}
                                required
                                defaultValue={
                                    '<article><h1>{{NUMERO_DOCUMENTO_PREVIO}}</h1><p>{{CONTENIDO_PRINCIPAL}}</p></article>'
                                }
                                className="w-full rounded-md border border-input bg-background p-3"
                            />
                            <InputError message={errors.contenido} />
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                Crear plantilla
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}
