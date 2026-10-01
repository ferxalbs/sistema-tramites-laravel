import { Form, Head, Link } from '@inertiajs/react';
import {
    Children,
    cloneElement,
    isValidElement,
    useState,
    type ReactElement,
    type ReactNode,
} from 'react';
import { Badge } from '@/components/ui/badge';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field as ShadcnField,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy,
    index,
    resetPassword,
    save,
    store,
    update,
} from '@/routes/admin/users';
import {
    index as studentsIndex,
    save as saveStudent,
    store as storeStudent,
} from '@/routes/assistant/students';

type Account = {
    id: number;
    estado?: 'activo' | 'pendiente' | 'inactivo' | 'rechazado';
    motivo_inactivacion?: string | null;
    rol: string;
    nombres: string | null;
    apellidos: string | null;
    dni: string | null;
    celular: string | null;
    email: string;
    correo_alternativo: string | null;
    cargo_institucional_id: number | null;
    codigo_docente: string | null;
    programa_estudio_id: number | null;
    condicion_academica: string | null;
    ciclo_actual: number | null;
    anio_egreso: number | null;
    direccion_residencia: string | null;
    especialidad: string | null;
    condicion_laboral: string | null;
    teacher_request?: {
        cargo: string | null;
        motivo: string;
        created_at: string;
    } | null;
};

type Props = {
    mode?: 'admin' | 'assistant';
    user: Account | null;
    programas: Array<{ id: number; nombre: string }>;
    cargos?: Array<{ id: number; nombre: string }>;
    passwordRules: string | null;
    viewerId?: number;
};

const roles = [
    { value: 'estudiante', label: 'Acceso estudiantil' },
    { value: 'asistente', label: 'Asistente de Gestión Documentaria' },
    { value: 'docente', label: 'Docente' },
    { value: 'administrador', label: 'Administrador' },
];

export default function UsuariosForm({
    mode = 'admin',
    user,
    programas,
    cargos = [],
    passwordRules,
    viewerId,
}: Props) {
    useFlashToast();
    const [role, setRole] = useState(user?.rol ?? 'estudiante');
    const [condition, setCondition] = useState(
        user?.condicion_academica ?? 'Estudiante',
    );
    const [program, setProgram] = useState<string | null>(
        user?.programa_estudio_id ? String(user.programa_estudio_id) : null,
    );
    const assistant = mode === 'assistant';
    const title = assistant
        ? user
            ? 'Editar estudiante/egresado'
            : 'Crear cuenta de estudiante/egresado'
        : user
          ? 'Editar usuario'
          : 'Crear usuario';

    return (
        <>
            <Head title={title} />
            <main className="p-4 md:p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>
                            {assistant
                                ? 'El rol se asigna como estudiante/egresado en el servidor. No puede cambiar roles ni estados desde este formulario.'
                                : 'El perfil requerido cambia según el rol. Las cuentas creadas aquí usan una contraseña temporal.'}
                        </CardDescription>
                        {user?.teacher_request && (
                            <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                                <p className="font-medium">Solicitud de acceso docente</p>
                                <p className="mt-1 text-muted-foreground">
                                    Cargo solicitado: {user.teacher_request.cargo ?? 'Sin cargo'}
                                </p>
                                <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                                    {user.teacher_request.motivo}
                                </p>
                            </div>
                        )}
                    </CardHeader>
                    <Form
                        {...(assistant
                            ? user
                                ? saveStudent.form({ user: user.id })
                                : storeStudent.form()
                            : user
                              ? save.form({ user: user.id })
                              : store.form())}
                        disableWhileProcessing
                        noValidate
                        resetOnSuccess={['password', 'password_confirmation']}
                        onBefore={() => window.confirm(
                            user
                                ? `¿Deseas guardar los cambios de ${user.nombres ?? user.email}?`
                                : '¿Deseas crear esta cuenta con los datos ingresados?',
                        )}
                    >
                        {({ errors, processing }) => (
                            <>
                                <CardContent>
                                    <FieldGroup className="gap-5">
                                    {!assistant && (
                                        <ShadcnField className="gap-2" data-invalid={errors.rol ? true : undefined}>
                                            <FieldLabel htmlFor="rol">Rol de acceso</FieldLabel>
                                            <Select
                                                name="rol"
                                                items={roles}
                                                value={role}
                                                onValueChange={(value) =>
                                                    setRole(
                                                        value ?? 'estudiante',
                                                    )
                                                }
                                                required
                                            >
                                                <SelectTrigger
                                                    id="rol"
                                                    aria-describedby={errors.rol ? 'rol-error' : undefined}
                                                    aria-invalid={errors.rol ? true : undefined}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {roles.map((item) => (
                                                            <SelectItem
                                                                key={item.value}
                                                                value={
                                                                    item.value
                                                                }
                                                            >
                                                                {item.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError id="rol-error">{errors.rol}</FieldError>
                                            {role === 'estudiante' && (
                                                <p className="text-sm text-muted-foreground">
                                                    El acceso estudiantil sirve para ambas situaciones; abajo elige si esta persona es Estudiante o Egresado.
                                                </p>
                                            )}
                                        </ShadcnField>
                                    )}

                                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                        <Field id="nombres" label="Nombres" error={errors.nombres}>
                                            <Input
                                                id="nombres"
                                                name="nombres"
                                                defaultValue={
                                                    user?.nombres ?? ''
                                                }
                                                maxLength={120}
                                                required
                                            />
                                        </Field>
                                        <Field id="apellidos" label="Apellidos" error={errors.apellidos}>
                                            <Input
                                                id="apellidos"
                                                name="apellidos"
                                                defaultValue={
                                                    user?.apellidos ?? ''
                                                }
                                                maxLength={120}
                                                required
                                            />
                                        </Field>
                                        <Field id="dni" label="DNI" error={errors.dni}>
                                            <Input
                                                id="dni"
                                                name="dni"
                                                defaultValue={user?.dni ?? ''}
                                                inputMode="numeric"
                                                pattern="[0-9]{8}"
                                                maxLength={8}
                                                required
                                            />
                                        </Field>
                                        <Field id="celular" label="Número de celular" error={errors.celular}>
                                            <Input
                                                id="celular"
                                                name="celular"
                                                defaultValue={
                                                    user?.celular ?? ''
                                                }
                                                maxLength={20}
                                                required
                                            />
                                        </Field>
                                        <Field id="email" label="Correo institucional" error={errors.email}>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                defaultValue={user?.email ?? ''}
                                                maxLength={190}
                                                required
                                            />
                                        </Field>
                                        <Field id="correo_alternativo" label="Correo alternativo (opcional)" error={errors.correo_alternativo}>
                                            <Input
                                                id="correo_alternativo"
                                                name="correo_alternativo"
                                                type="email"
                                                defaultValue={
                                                    user?.correo_alternativo ??
                                                    ''
                                                }
                                                maxLength={190}
                                            />
                                        </Field>
                                    </FieldGroup>

                                    {(role === 'estudiante' ||
                                        role === 'docente') && (
                                        <ShadcnField className="gap-2" data-invalid={errors.programa_estudio_id ? true : undefined}>
                                            <FieldLabel htmlFor="programa_estudio_id">
                                                Programa de estudios
                                            </FieldLabel>
                                            <Select
                                                name="programa_estudio_id"
                                                items={programas.map(
                                                    (item) => ({
                                                        value: String(item.id),
                                                        label: item.nombre,
                                                    }),
                                                )}
                                                value={program}
                                                onValueChange={setProgram}
                                                required
                                            >
                                                <SelectTrigger
                                                    id="programa_estudio_id"
                                                    aria-describedby={errors.programa_estudio_id ? 'programa_estudio_id-error' : undefined}
                                                    aria-invalid={errors.programa_estudio_id ? true : undefined}
                                                >
                                                    <SelectValue placeholder="Seleccione un programa" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {programas.map(
                                                            (item) => (
                                                                <SelectItem
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    value={String(
                                                                        item.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        item.nombre
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError id="programa_estudio_id-error">{errors.programa_estudio_id}</FieldError>
                                            {programas.length === 0 && (
                                                <p className="text-sm text-destructive">
                                                    No hay programas de estudios
                                                    configurados. Ejecute las
                                                    migraciones antes de crear
                                                    cuentas.
                                                </p>
                                            )}
                                        </ShadcnField>
                                    )}

                                    {!assistant && role !== 'estudiante' && (
                                        <ShadcnField className="gap-2" data-invalid={errors.cargo_institucional_id ? true : undefined}>
                                            <FieldLabel htmlFor="cargo_institucional_id">
                                                Cargo institucional
                                            </FieldLabel>
                                            <Select
                                                name="cargo_institucional_id"
                                                defaultValue={
                                                    user?.cargo_institucional_id
                                                        ? String(
                                                              user.cargo_institucional_id,
                                                          )
                                                        : 'sin_cargo'
                                                }
                                                items={[
                                                    {
                                                        value: 'sin_cargo',
                                                        label: 'Sin cargo asignado',
                                                    },
                                                    ...cargos.map((cargo) => ({
                                                        value: String(cargo.id),
                                                        label: cargo.nombre,
                                                    })),
                                                ]}
                                            >
                                                <SelectTrigger
                                                    id="cargo_institucional_id"
                                                    aria-describedby={errors.cargo_institucional_id ? 'cargo_institucional_id-error' : undefined}
                                                    aria-invalid={errors.cargo_institucional_id ? true : undefined}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="sin_cargo">
                                                            Sin cargo asignado
                                                        </SelectItem>
                                                        {cargos.map((cargo) => (
                                                            <SelectItem
                                                                key={cargo.id}
                                                                value={String(
                                                                    cargo.id,
                                                                )}
                                                            >
                                                                {cargo.nombre}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError id="cargo_institucional_id-error">{errors.cargo_institucional_id}</FieldError>
                                        </ShadcnField>
                                    )}

                                    {role === 'estudiante' && (
                                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                            <div className="rounded-xl border bg-muted/30 p-3 text-sm sm:col-span-2">
                                                El DNI registrado arriba también
                                                será el código del estudiante.
                                            </div>
                                            <ShadcnField className="gap-2" data-invalid={errors.condicion_academica ? true : undefined}>
                                                <FieldLabel htmlFor="condicion_academica">
                                                    Situación académica
                                                </FieldLabel>
                                                <Select
                                                    name="condicion_academica"
                                                    items={[
                                                        {
                                                            value: 'Estudiante',
                                                            label: 'Estudiante',
                                                        },
                                                        {
                                                            value: 'Egresado',
                                                            label: 'Egresado',
                                                        },
                                                    ]}
                                                    value={condition}
                                                    onValueChange={(value) =>
                                                        setCondition(
                                                            value ??
                                                                'Estudiante',
                                                        )
                                                    }
                                                    required
                                                >
                                                    <SelectTrigger
                                                        id="condicion_academica"
                                                        aria-describedby={errors.condicion_academica ? 'condicion_academica-error' : undefined}
                                                        aria-invalid={errors.condicion_academica ? true : undefined}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            <SelectItem value="Estudiante">
                                                                Estudiante
                                                            </SelectItem>
                                                            <SelectItem value="Egresado">
                                                                Egresado
                                                            </SelectItem>
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                                <FieldError id="condicion_academica-error">{errors.condicion_academica}</FieldError>
                                                <p className="text-sm text-muted-foreground">
                                                    Selecciona una sola opción: Estudiante o Egresado.
                                                </p>
                                            </ShadcnField>
                                            {condition === 'Estudiante' ? (
                                                <Field id="ciclo_actual" label="Ciclo actual" error={errors.ciclo_actual}>
                                                    <Input
                                                        id="ciclo_actual"
                                                        name="ciclo_actual"
                                                        type="number"
                                                        min={1}
                                                        max={6}
                                                        defaultValue={
                                                            user?.ciclo_actual ??
                                                            ''
                                                        }
                                                        required
                                                    />
                                                </Field>
                                            ) : (
                                                <Field id="anio_egreso" label="Año de egreso" error={errors.anio_egreso}>
                                                    <Input
                                                        id="anio_egreso"
                                                        name="anio_egreso"
                                                        type="number"
                                                        min={1950}
                                                        max={new Date().getFullYear()}
                                                        defaultValue={
                                                            user?.anio_egreso ??
                                                            ''
                                                        }
                                                        required
                                                    />
                                                </Field>
                                            )}
                                            <Field id="direccion_residencia" label="Dirección (opcional)" error={errors.direccion_residencia}>
                                                <Input
                                                    id="direccion_residencia"
                                                    name="direccion_residencia"
                                                    defaultValue={
                                                        user?.direccion_residencia ??
                                                        ''
                                                    }
                                                    maxLength={255}
                                                />
                                            </Field>
                                        </FieldGroup>
                                    )}

                                    {role === 'docente' && (
                                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                            <Field id="codigo_docente" label="Código docente (opcional)" error={errors.codigo_docente}>
                                                <Input
                                                    id="codigo_docente"
                                                    name="codigo_docente"
                                                    defaultValue={
                                                        user?.codigo_docente ??
                                                        ''
                                                    }
                                                    maxLength={40}
                                                />
                                            </Field>
                                            <Field id="especialidad" label="Especialidad (opcional)" error={errors.especialidad}>
                                                <Input
                                                    id="especialidad"
                                                    name="especialidad"
                                                    defaultValue={
                                                        user?.especialidad ?? ''
                                                    }
                                                    maxLength={160}
                                                />
                                            </Field>
                                            <Field id="condicion_laboral" label="Condición laboral (opcional)" error={errors.condicion_laboral}>
                                                <Input
                                                    id="condicion_laboral"
                                                    name="condicion_laboral"
                                                    defaultValue={
                                                        user?.condicion_laboral ??
                                                        ''
                                                    }
                                                    maxLength={100}
                                                />
                                            </Field>
                                        </FieldGroup>
                                    )}

                                    {!user && (
                                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                            <Field id="password" label="Contraseña temporal" error={errors.password}>
                                                <PasswordInput
                                                    id="password"
                                                    name="password"
                                                    autoComplete="new-password"
                                                    passwordrules={
                                                        passwordRules ??
                                                        undefined
                                                    }
                                                    required
                                                />
                                            </Field>
                                            <Field id="password_confirmation" label="Confirmar contraseña" error={errors.password_confirmation}>
                                                <PasswordInput
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    autoComplete="new-password"
                                                    passwordrules={
                                                        passwordRules ??
                                                        undefined
                                                    }
                                                    required
                                                />
                                            </Field>
                                            {!assistant && (
                                                <ShadcnField orientation="horizontal" className="sm:col-span-2">
                                                    <Checkbox
                                                        id="activar_inmediatamente"
                                                        name="activar_inmediatamente"
                                                        value="1"
                                                        defaultChecked
                                                    />
                                                    <FieldLabel htmlFor="activar_inmediatamente">
                                                        Crear activa y
                                                        verificada
                                                        administrativamente
                                                    </FieldLabel>
                                                </ShadcnField>
                                            )}
                                        </FieldGroup>
                                    )}

                                    {role === 'administrador' &&
                                        user?.rol !== 'administrador' && (
                                            <ShadcnField data-invalid={errors.confirmar_administrador ? true : undefined}>
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="confirmar_administrador"
                                                        name="confirmar_administrador"
                                                        value="1"
                                                        required
                                                        aria-describedby={errors.confirmar_administrador ? 'confirmar_administrador-error' : undefined}
                                                        aria-invalid={errors.confirmar_administrador ? true : undefined}
                                                    />
                                                    <FieldLabel htmlFor="confirmar_administrador">
                                                        Confirmo la asignación
                                                        del rol Administrador
                                                    </FieldLabel>
                                                </div>
                                                <FieldError id="confirmar_administrador-error">{errors.confirmar_administrador}</FieldError>
                                            </ShadcnField>
                                        )}
                                    </FieldGroup>
                                </CardContent>
                                <CardFooter className="flex flex-col items-start gap-3">
                                    {Object.keys(errors).length > 0 && (
                                        <div role="alert" className="w-full rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                                            <p className="font-medium">No se pudo guardar el usuario. Corrige estos campos:</p>
                                            <ul className="mt-1 list-inside list-disc">
                                                {Object.entries(errors).map(([field, message]) => <li key={field}>{message}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={processing}>
                                            {user
                                                ? 'Guardar cambios'
                                                : assistant
                                                  ? 'Crear cuenta provisional'
                                                  : 'Crear usuario'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            nativeButton={false}
                                            render={
                                                <Link
                                                    href={
                                                        assistant
                                                            ? studentsIndex()
                                                            : index()
                                                    }
                                                />
                                            }
                                        >
                                            Volver
                                        </Button>
                                    </div>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
                {!assistant && user && (
                    <AccountActions user={user} viewerId={viewerId} />
                )}
            </main>
        </>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: ReactNode;
    error?: string;
    children: ReactNode;
}) {
    const errorId = `${id}-error`;
    const invalid = Boolean(error);
    const control = Children.map(children, (child, index) => {
        if (index !== 0 || !isValidElement(child)) {
            return child;
        }

        return cloneElement(
            child as ReactElement<{
                id?: string;
                'aria-describedby'?: string;
                'aria-invalid'?: boolean;
            }>,
            {
                id,
                'aria-describedby': invalid ? errorId : undefined,
                'aria-invalid': invalid ? true : undefined,
            },
        );
    });

    return (
        <ShadcnField className="gap-2" data-invalid={invalid ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {control}
            <FieldError id={errorId}>{error}</FieldError>
        </ShadcnField>
    );
}

function AccountActions({
    user,
    viewerId,
}: {
    user: Account;
    viewerId?: number;
}) {
    const status = user.estado ?? 'pendiente';
    const statusLabels = {
        activo: 'Activa',
        pendiente: 'Pendiente',
        inactivo: 'Inactiva',
        rechazado: 'Rechazada',
    } as const;
    const isCurrentUser = user.id === viewerId;

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Estado y acceso</CardTitle>
                    <CardDescription>
                        Estado actual de la cuenta: <Badge variant={status === 'activo' ? 'default' : 'secondary'}>{statusLabels[status]}</Badge>
                        {user.motivo_inactivacion && (
                            <span className="mt-2 block">
                                Motivo registrado: {user.motivo_inactivacion}
                            </span>
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {status !== 'activo' && (
                        <Form
                            {...update.form({ user: user.id })}
                            disableWhileProcessing
                            onBefore={() => window.confirm(`¿Deseas activar la cuenta de ${user.nombres ?? user.email}?`)}
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-2">
                                    <input type="hidden" name="accion" value="activate" />
                                    <Button type="submit" disabled={processing}>
                                        Activar cuenta
                                    </Button>
                                    <FieldError id="activar-cuenta-error">{errors.accion}</FieldError>
                                </div>
                            )}
                        </Form>
                    )}

                    {status === 'activo' && !isCurrentUser && (
                        <Form
                            {...update.form({ user: user.id })}
                            disableWhileProcessing
                            onBefore={() => window.confirm(`¿Deseas desactivar la cuenta de ${user.nombres ?? user.email}?`)}
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-2">
                                    <input type="hidden" name="accion" value="deactivate" />
                                    <Field id="motivo-desactivacion" label="Motivo de desactivación" error={errors.motivo || errors.accion}>
                                        <Textarea
                                            id="motivo-desactivacion"
                                            name="motivo"
                                            required
                                            maxLength={500}
                                            rows={3}
                                        />
                                    </Field>
                                    <Button type="submit" variant="outline" disabled={processing}>
                                        Desactivar cuenta
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}

                    {status === 'pendiente' && (
                        <Form
                            {...update.form({ user: user.id })}
                            disableWhileProcessing
                            onBefore={() => window.confirm(`¿Deseas rechazar la solicitud de ${user.nombres ?? user.email}?`)}
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-2">
                                    <input type="hidden" name="accion" value="reject" />
                                    <Field id="motivo-rechazo" label="Motivo de rechazo" error={errors.motivo || errors.accion}>
                                        <Textarea
                                            id="motivo-rechazo"
                                            name="motivo"
                                            required
                                            maxLength={500}
                                            rows={3}
                                        />
                                    </Field>
                                    <Button type="submit" variant="destructive" disabled={processing}>
                                        Rechazar solicitud
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}

                    {status === 'activo' && (
                        <Form
                            {...resetPassword.form({ user: user.id })}
                            disableWhileProcessing
                            onBefore={() => window.confirm(`¿Deseas enviar un enlace de restablecimiento a ${user.email}?`)}
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">
                                        Se enviará un enlace de un solo uso al correo institucional.
                                    </p>
                                    <FieldError id="restablecer-contrasena-error">{errors.reset}</FieldError>
                                    <Button type="submit" variant="outline" disabled={processing}>
                                        Enviar enlace para restablecer contraseña
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                </CardContent>
            </Card>

            {!isCurrentUser && (
                <Card className="border-destructive/40">
                    <CardHeader>
                        <CardTitle>Eliminar usuario</CardTitle>
                        <CardDescription>
                            La eliminación es permanente. Si la cuenta tiene trámites o actividad vinculada, el sistema la bloqueará para conservar el historial; en ese caso, desactívala.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...destroy.form({ user: user.id })}
                            disableWhileProcessing
                            onBefore={() => window.confirm(`¿Eliminar permanentemente la cuenta de ${user.nombres ?? user.email}? Esta acción no se puede deshacer.`)}
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-2">
                                    <FieldError id="eliminar-usuario-error">{errors.delete}</FieldError>
                                    <Button type="submit" variant="destructive" disabled={processing}>
                                        Eliminar usuario
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
