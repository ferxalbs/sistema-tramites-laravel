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
import { update } from '@/routes/admin/types';

type TramiteType = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    clasificacion_sugerida: string | null;
    es_demostracion: boolean | number;
    activo: boolean | number;
};

export default function TiposTramite({ tipos }: { tipos: TramiteType[] }) {
    return (
        <>
            <Head title="Tipos de trámite" />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>Tipos de trámite</CardTitle>
                        <CardDescription>
                            Catálogo provisional del sistema fuente. Un tipo
                            inactivo ya no puede seleccionarse en una nueva
                            recepción.
                        </CardDescription>
                    </CardHeader>
                </Card>
                {tipos.map((tipo) => (
                    <Card key={tipo.id}>
                        <CardHeader>
                            <CardTitle>{tipo.codigo}</CardTitle>
                            <CardDescription>
                                {tipo.clasificacion_sugerida
                                    ? `Clasificación: ${tipo.clasificacion_sugerida}. `
                                    : 'Todas las clasificaciones. '}
                                {tipo.es_demostracion ? 'Provisional.' : ''}
                            </CardDescription>
                        </CardHeader>
                        <Form
                            {...update.form({ type: tipo.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <Label htmlFor={`nombre-${tipo.id}`}>
                                            Nombre
                                        </Label>
                                        <Input
                                            id={`nombre-${tipo.id}`}
                                            name="nombre"
                                            required
                                            minLength={2}
                                            maxLength={140}
                                            defaultValue={tipo.nombre}
                                        />
                                        <InputError message={errors.nombre} />
                                        <Label
                                            htmlFor={`descripcion-${tipo.id}`}
                                        >
                                            Descripción
                                        </Label>
                                        <Input
                                            id={`descripcion-${tipo.id}`}
                                            name="descripcion"
                                            maxLength={255}
                                            defaultValue={
                                                tipo.descripcion ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.descripcion}
                                        />
                                        <Label htmlFor={`activo-${tipo.id}`}>
                                            Estado
                                        </Label>
                                        <Select
                                            name="activo"
                                            defaultValue={
                                                tipo.activo ? '1' : '0'
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
                                                id={`activo-${tipo.id}`}
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
