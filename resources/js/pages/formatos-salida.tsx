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
import { update } from '@/routes/admin/output-formats';

type OutputFormat = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    permite_modalidad_multiple: boolean | number;
    activo: boolean | number;
};

export default function FormatosSalida({
    formatos,
}: {
    formatos: OutputFormat[];
}) {
    return (
        <>
            <Head title="Formatos de salida" />
            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Formatos de salida
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Informe y Memorando del catálogo institucional. Un formato inactivo no admite nuevos borradores.
                    </p>
                </header>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {formatos.map((formato) => (
                        <Card key={formato.id} className="transition-all hover:border-foreground/20">
                            <CardHeader>
                                <CardTitle>{formato.codigo}</CardTitle>
                                <CardDescription>
                                    {formato.permite_modalidad_multiple
                                        ? 'Admite memorando simple o múltiple.'
                                        : 'Sin modalidad múltiple.'}
                                </CardDescription>
                            </CardHeader>
                        <Form
                            {...update.form({ format: formato.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <Label htmlFor={`nombre-${formato.id}`}>
                                            Nombre
                                        </Label>
                                        <Input
                                            id={`nombre-${formato.id}`}
                                            name="nombre"
                                            required
                                            minLength={2}
                                            maxLength={120}
                                            defaultValue={formato.nombre}
                                        />
                                        <InputError message={errors.nombre} />
                                        <Label
                                            htmlFor={`descripcion-${formato.id}`}
                                        >
                                            Descripción
                                        </Label>
                                        <Input
                                            id={`descripcion-${formato.id}`}
                                            name="descripcion"
                                            maxLength={255}
                                            defaultValue={
                                                formato.descripcion ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.descripcion}
                                        />
                                        <Label htmlFor={`activo-${formato.id}`}>
                                            Estado
                                        </Label>
                                        <Select
                                            name="activo"
                                            defaultValue={
                                                formato.activo ? '1' : '0'
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
                                                id={`activo-${formato.id}`}
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
                </div>
            </div>
        </>
    );
}
