import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardAction,
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
import { resend } from '@/routes/registration';
import { store } from '@/routes/register';
import { useState } from 'react';

type Props = {
    passwordRules: string;
    programas: Array<{ id: number; nombre: string }>;
    status?: string;
};

export default function Register({ passwordRules, programas, status }: Props) {
    const [condition, setCondition] = useState('Estudiante');
    const [program, setProgram] = useState<string | null>(null);

    return (
        <>
            <Head title="Registro de Estudiante/Egresado" />

            <Card className="w-full max-w-3xl">
                <CardHeader>
                    <CardTitle>Registro de Estudiante/Egresado</CardTitle>
                    <CardDescription>
                        La cuenta quedará pendiente de verificación de correo y validación administrativa. Los trámites se reciben físicamente en Mesa de Partes.
                    </CardDescription>
                    <CardAction>
                        <Button
                            variant="link"
                            render={<Link href={login()} />}
                        >
                            Iniciar sesión
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    {status && <p role="status" className="mb-4 rounded-xl bg-muted p-3 text-sm">{status}</p>}
                    <Form
                        id="register-form"
                        {...store.form()}
                        resetOnSuccess={['password', 'password_confirmation']}
                        disableWhileProcessing
                    >
                        {({ errors }) => (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="nombres">Nombres</Label>
                                    <Input
                                        id="nombres"
                                        type="text"
                                        required
                                        autoFocus
                                        autoComplete="given-name"
                                        name="nombres"
                                        maxLength={120}
                                    />
                                    <InputError message={errors.nombres} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="apellidos">Apellidos</Label>
                                    <Input id="apellidos" name="apellidos" required autoComplete="family-name" maxLength={120} />
                                    <InputError message={errors.apellidos} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="dni">DNI</Label>
                                    <Input id="dni" name="dni" required inputMode="numeric" pattern="[0-9]{8}" maxLength={8} />
                                    <InputError message={errors.dni} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="celular">Número de celular</Label>
                                    <Input id="celular" name="celular" required autoComplete="tel" maxLength={20} />
                                    <InputError message={errors.celular} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Correo institucional</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        name="email"
                                        pattern="a\.[a-z0-9._-]+@seoane\.edu\.pe"
                                        title="Use el formato a.usuario@seoane.edu.pe"
                                        placeholder="a.usuario@seoane.edu.pe"
                                        maxLength={190}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="correo_alternativo">Correo alternativo (opcional)</Label>
                                    <Input id="correo_alternativo" name="correo_alternativo" type="email" maxLength={190} />
                                    <InputError message={errors.correo_alternativo} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="programa_estudio_id">Programa de estudios</Label>
                                    <Select name="programa_estudio_id" items={programas.map((item) => ({ value: String(item.id), label: item.nombre }))} value={program} onValueChange={setProgram} required>
                                        <SelectTrigger id="programa_estudio_id" className="w-full"><SelectValue placeholder="Seleccione un programa" /></SelectTrigger>
                                        <SelectContent><SelectGroup>{programas.map((item) => <SelectItem key={item.id} value={String(item.id)}>{item.nombre}</SelectItem>)}</SelectGroup></SelectContent>
                                    </Select>
                                    <InputError message={errors.programa_estudio_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="condicion_academica">Condición académica</Label>
                                    <Select name="condicion_academica" items={[{ value: 'Estudiante', label: 'Estudiante' }, { value: 'Egresado', label: 'Egresado' }]} value={condition} onValueChange={(value) => setCondition(value ?? 'Estudiante')} required>
                                        <SelectTrigger id="condicion_academica" className="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent><SelectGroup><SelectItem value="Estudiante">Estudiante</SelectItem><SelectItem value="Egresado">Egresado</SelectItem></SelectGroup></SelectContent>
                                    </Select>
                                    <InputError message={errors.condicion_academica} />
                                </div>
                                {condition === 'Estudiante' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="ciclo_actual">Ciclo actual</Label>
                                        <Input id="ciclo_actual" name="ciclo_actual" type="number" min={1} max={10} required />
                                        <InputError message={errors.ciclo_actual} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="anio_egreso">Año de egreso</Label>
                                        <Input id="anio_egreso" name="anio_egreso" type="number" min={1950} max={new Date().getFullYear()} required />
                                        <InputError message={errors.anio_egreso} />
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="direccion_residencia">Dirección de residencia (opcional)</Label>
                                    <Input id="direccion_residencia" name="direccion_residencia" maxLength={255} />
                                    <InputError message={errors.direccion_residencia} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Contraseña</Label>
                                    <PasswordInput
                                        id="password"
                                        required
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="••••••••"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirmar contraseña
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="••••••••"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                                <div className="col-span-full flex items-start gap-2">
                                    <Checkbox id="acepta_terminos" name="acepta_terminos" value="1" required />
                                    <div>
                                        <Label htmlFor="acepta_terminos">Acepto las condiciones básicas de uso y el tratamiento de mis datos para gestionar mi cuenta.</Label>
                                        <InputError message={errors.acepta_terminos} />
                                    </div>
                                </div>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <Button
                        type="submit"
                        form="register-form"
                        className="w-full"
                        data-test="register-user-button"
                    >
                        Registrar cuenta
                    </Button>
                </CardFooter>
            </Card>
            <Card className="mt-4 w-full max-w-3xl">
                <CardHeader><CardTitle>Reenviar verificación</CardTitle></CardHeader>
                <CardContent>
                    <Form {...resend.form()} disableWhileProcessing className="flex flex-wrap gap-2">
                        {({ errors }) => <>
                            <div className="min-w-60 flex-1"><Input name="email" type="email" aria-label="Correo institucional para reenviar" placeholder="a.usuario@seoane.edu.pe" required /><InputError message={errors.email} /></div>
                            <Button type="submit" variant="outline">Solicitar enlace</Button>
                        </>}
                    </Form>
                </CardContent>
            </Card>
        </>
    );
}
