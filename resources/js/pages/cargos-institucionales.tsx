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
import { update } from '@/routes/admin/positions';

type Position = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    activo: boolean | number;
};

export default function CargosInstitucionales({
    cargos,
}: {
    cargos: Position[];
}) {
    return (
        <>
            <Head title="Cargos institucionales" />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>Cargos institucionales</CardTitle>
                        <CardDescription>
                            Los cargos describen funciones institucionales. No
                            cambian el rol ni los permisos del sistema.
                        </CardDescription>
                    </CardHeader>
                </Card>
                {cargos.map((cargo) => (
                    <Card key={cargo.id}>
                        <CardHeader>
                            <CardTitle>{cargo.codigo}</CardTitle>
                        </CardHeader>
                        <Form
                            {...update.form({ position: cargo.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <Label htmlFor={`nombre-${cargo.id}`}>
                                            Nombre
                                        </Label>
                                        <Input
                                            id={`nombre-${cargo.id}`}
                                            name="nombre"
                                            required
                                            minLength={2}
                                            maxLength={140}
                                            defaultValue={cargo.nombre}
                                        />
                                        <InputError message={errors.nombre} />

                                        <Label
                                            htmlFor={`descripcion-${cargo.id}`}
                                        >
                                            Descripción
                                        </Label>
                                        <Input
                                            id={`descripcion-${cargo.id}`}
                                            name="descripcion"
                                            maxLength={255}
                                            defaultValue={
                                                cargo.descripcion ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.descripcion}
                                        />

                                        <Label htmlFor={`activo-${cargo.id}`}>
                                            Estado
                                        </Label>
                                        <Select
                                            name="activo"
                                            defaultValue={
                                                cargo.activo ? '1' : '0'
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
                                                id={`activo-${cargo.id}`}
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
