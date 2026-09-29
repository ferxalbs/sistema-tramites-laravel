import { Form, Head } from '@inertiajs/react';
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
import { update } from '@/routes/admin/classifications';

type Classification = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    requiere_estudiante: boolean | number;
    activo: boolean | number;
};

export default function ClasificacionesExpediente({
    clasificaciones,
}: {
    clasificaciones: Classification[];
}) {
    return (
        <>
            <Head title="Clasificaciones de expedientes" />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>Clasificaciones de expedientes</CardTitle>
                        <CardDescription>
                            Los códigos y el requisito de estudiante se
                            conservan. Una clasificación inactiva ya no puede
                            seleccionarse al recibir un expediente.
                        </CardDescription>
                    </CardHeader>
                </Card>
                {clasificaciones.map((clasificacion) => (
                    <Card key={clasificacion.id}>
                        <CardHeader>
                            <CardTitle>{clasificacion.codigo}</CardTitle>
                            <CardDescription>
                                {clasificacion.requiere_estudiante
                                    ? 'Exige estudiante o egresado relacionado.'
                                    : 'No exige estudiante relacionado.'}
                            </CardDescription>
                        </CardHeader>
                        <Form
                            {...update.form({
                                classification: clasificacion.id,
                            })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <Label
                                            htmlFor={`nombre-${clasificacion.id}`}
                                        >
                                            Nombre
                                        </Label>
                                        <Input
                                            id={`nombre-${clasificacion.id}`}
                                            name="nombre"
                                            required
                                            minLength={2}
                                            maxLength={120}
                                            defaultValue={clasificacion.nombre}
                                        />
                                        <InputError message={errors.nombre} />
                                        <Label
                                            htmlFor={`descripcion-${clasificacion.id}`}
                                        >
                                            Descripción
                                        </Label>
                                        <Input
                                            id={`descripcion-${clasificacion.id}`}
                                            name="descripcion"
                                            maxLength={255}
                                            defaultValue={
                                                clasificacion.descripcion ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.descripcion}
                                        />
                                        <Label
                                            htmlFor={`activo-${clasificacion.id}`}
                                        >
                                            Estado
                                        </Label>
                                        <Select
                                            name="activo"
                                            defaultValue={
                                                clasificacion.activo ? '1' : '0'
                                            }
                                            items={[
                                                { value: '1', label: 'Activo' },
                                                {
                                                    value: '0',
                                                    label: 'Inactivo',
                                                },
                                            ]}
                                            required
                                        >
                                            <SelectTrigger
                                                id={`activo-${clasificacion.id}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="1">
                                                        Activo
                                                    </SelectItem>
                                                    <SelectItem value="0">
                                                        Inactivo
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.activo} />
                                    </CardContent>
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar
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
