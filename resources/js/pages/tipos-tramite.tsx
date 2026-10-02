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
            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Tipos de trámite
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Catálogo provisional del sistema. Un tipo inactivo no
                        puede seleccionarse en nuevas recepciones.
                    </p>
                </header>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {tipos.map((tipo) => (
                        <Card
                            key={tipo.id}
                            className="transition-all hover:border-foreground/20"
                        >
                            <CardHeader>
                                <CardTitle>{tipo.nombre}</CardTitle>
                                <CardDescription>
                                    {tipo.codigo} ·{' '}
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
                                            <Label
                                                htmlFor={`nombre-${tipo.id}`}
                                            >
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
                                            <InputError
                                                message={errors.nombre}
                                            />
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
                                            <Label
                                                htmlFor={`activo-${tipo.id}`}
                                            >
                                                Estado
                                            </Label>
                                            <Select
                                                name="activo"
                                                defaultValue={
                                                    tipo.activo ? '1' : '0'
                                                }
                                                items={[
                                                    {
                                                        value: '1',
                                                        label: 'Activo',
                                                    },
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
                                            <InputError
                                                message={errors.activo}
                                            />
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
                </div>
            </div>
        </>
    );
}
