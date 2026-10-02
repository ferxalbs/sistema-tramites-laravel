import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
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
import { login } from '@/routes';
import { store } from '@/routes/teacher-access';

type Option = { id: number; nombre: string };

export default function TeacherAccessRequest({
    programas,
    cargos,
    status,
}: {
    programas: Option[];
    cargos: Option[];
    status?: string;
}) {
    const [program, setProgram] = useState<string | null>(null);
    const [position, setPosition] = useState<string | null>(null);

    return (
        <>
            <Head title="Solicitud de acceso docente" />
            <Card>
                <CardHeader>
                    <CardTitle>Solicitar acceso institucional</CardTitle>
                    <CardDescription>
                        Exclusivo para docentes. La cuenta requiere verificar el
                        correo y aprobación administrativa.
                    </CardDescription>
                </CardHeader>
                {status ? (
                    <CardContent>
                        <p role="status">{status}</p>
                        <Button variant="link" render={<Link href={login()} />}>
                            Iniciar sesión
                        </Button>
                    </CardContent>
                ) : (
                    <Form {...store.form()} disableWhileProcessing>
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-4">
                                    <Label htmlFor="nombres">Nombres</Label>
                                    <Input
                                        id="nombres"
                                        name="nombres"
                                        required
                                        minLength={2}
                                        maxLength={120}
                                        autoComplete="given-name"
                                    />
                                    <InputError message={errors.nombres} />

                                    <Label htmlFor="apellidos">Apellidos</Label>
                                    <Input
                                        id="apellidos"
                                        name="apellidos"
                                        required
                                        minLength={2}
                                        maxLength={120}
                                        autoComplete="family-name"
                                    />
                                    <InputError message={errors.apellidos} />

                                    <Label htmlFor="dni">DNI</Label>
                                    <Input
                                        id="dni"
                                        name="dni"
                                        required
                                        inputMode="numeric"
                                        pattern="[0-9]{8}"
                                        minLength={8}
                                        maxLength={8}
                                    />
                                    <InputError message={errors.dni} />

                                    <Label htmlFor="email">
                                        Correo institucional
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        maxLength={190}
                                        autoComplete="email"
                                    />
                                    <InputError message={errors.email} />

                                    <Label htmlFor="programa_estudio_id">
                                        Programa de estudios (opcional)
                                    </Label>
                                    <Select
                                        name="programa_estudio_id"
                                        items={[
                                            {
                                                value: 'sin_programa',
                                                label: 'Sin programa (curso complementario)',
                                            },
                                            ...programas.map((item) => ({
                                                value: String(item.id),
                                                label: item.nombre,
                                            })),
                                        ]}
                                        value={program ?? 'sin_programa'}
                                        onValueChange={(value) =>
                                            setProgram(
                                                value === 'sin_programa'
                                                    ? null
                                                    : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="programa_estudio_id">
                                            <SelectValue placeholder="Seleccione un programa" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="sin_programa">
                                                    Sin programa (curso
                                                    complementario)
                                                </SelectItem>
                                                {programas.map((item) => (
                                                    <SelectItem
                                                        key={item.id}
                                                        value={String(item.id)}
                                                    >
                                                        {item.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.programa_estudio_id}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Selecciona un programa solo si
                                        corresponde a tu labor docente.
                                    </p>

                                    <Label htmlFor="cargo_institucional_id">
                                        Cargo institucional
                                    </Label>
                                    <Select
                                        name="cargo_institucional_id"
                                        items={cargos.map((item) => ({
                                            value: String(item.id),
                                            label: item.nombre,
                                        }))}
                                        value={position}
                                        onValueChange={setPosition}
                                        required
                                    >
                                        <SelectTrigger id="cargo_institucional_id">
                                            <SelectValue placeholder="Seleccione un cargo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {cargos.map((item) => (
                                                    <SelectItem
                                                        key={item.id}
                                                        value={String(item.id)}
                                                    >
                                                        {item.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.cargo_institucional_id}
                                    />

                                    <Label htmlFor="motivo">
                                        Motivo de solicitud
                                    </Label>
                                    <Input
                                        id="motivo"
                                        name="motivo"
                                        required
                                        minLength={10}
                                        maxLength={500}
                                    />
                                    <InputError message={errors.motivo} />
                                </CardContent>
                                <CardFooter>
                                    <Button type="submit" disabled={processing}>
                                        Enviar solicitud
                                    </Button>
                                    <Button
                                        variant="link"
                                        render={<Link href={login()} />}
                                    >
                                        Iniciar sesión
                                    </Button>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                )}
            </Card>
        </>
    );
}
