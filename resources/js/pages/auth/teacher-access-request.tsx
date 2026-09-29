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
                                        Programa o área
                                    </Label>
                                    <Select
                                        name="programa_estudio_id"
                                        items={programas.map((item) => ({
                                            value: String(item.id),
                                            label: item.nombre,
                                        }))}
                                        value={program}
                                        onValueChange={setProgram}
                                        required
                                    >
                                        <SelectTrigger id="programa_estudio_id">
                                            <SelectValue placeholder="Seleccione un programa" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
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
