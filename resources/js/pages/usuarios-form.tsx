import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { index, save, store } from '@/routes/admin/users';
import {
    index as studentsIndex,
    save as saveStudent,
    store as storeStudent,
} from '@/routes/assistant/students';

type Account = {
    id: number;
    rol: string;
    nombres: string | null;
    apellidos: string | null;
    dni: string | null;
    celular: string | null;
    email: string;
    correo_alternativo: string | null;
    cargo_institucional_id: number | null;
    codigo_estudiante: string | null;
    codigo_docente: string | null;
    programa_estudio_id: number | null;
    condicion_academica: string | null;
    ciclo_actual: number | null;
    anio_egreso: number | null;
    direccion_residencia: string | null;
    especialidad: string | null;
    condicion_laboral: string | null;
};

type Props = {
    mode?: 'admin' | 'assistant';
    user: Account | null;
    programas: Array<{ id: number; nombre: string }>;
    cargos?: Array<{ id: number; nombre: string }>;
    passwordRules: string | null;
};

const roles = [
    { value: 'estudiante', label: 'Estudiante/Egresado' },
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
}: Props) {
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
                        resetOnSuccess={['password', 'password_confirmation']}
                    >
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-5">
                                    {!assistant && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="rol">Rol</Label>
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
                                                <SelectTrigger id="rol">
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
                                            <InputError message={errors.rol} />
                                        </div>
                                    )}

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="nombres">
                                                Nombres
                                            </Label>
                                            <Input
                                                id="nombres"
                                                name="nombres"
                                                defaultValue={
                                                    user?.nombres ?? ''
                                                }
                                                maxLength={120}
                                                required
                                            />
                                            <InputError
                                                message={errors.nombres}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="apellidos">
                                                Apellidos
                                            </Label>
                                            <Input
                                                id="apellidos"
                                                name="apellidos"
                                                defaultValue={
                                                    user?.apellidos ?? ''
                                                }
                                                maxLength={120}
                                                required
                                            />
                                            <InputError
                                                message={errors.apellidos}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="dni">DNI</Label>
                                            <Input
                                                id="dni"
                                                name="dni"
                                                defaultValue={user?.dni ?? ''}
                                                inputMode="numeric"
                                                pattern="[0-9]{8}"
                                                maxLength={8}
                                                required
                                            />
                                            <InputError message={errors.dni} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="celular">
                                                Número de celular
                                            </Label>
                                            <Input
                                                id="celular"
                                                name="celular"
                                                defaultValue={
                                                    user?.celular ?? ''
                                                }
                                                maxLength={20}
                                                required
                                            />
                                            <InputError
                                                message={errors.celular}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                Correo institucional
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                defaultValue={user?.email ?? ''}
                                                maxLength={190}
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="correo_alternativo">
                                                Correo alternativo (opcional)
                                            </Label>
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
                                            <InputError
                                                message={
                                                    errors.correo_alternativo
                                                }
                                            />
                                        </div>
                                    </div>

                                    {(role === 'estudiante' ||
                                        role === 'docente') && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="programa_estudio_id">
                                                Programa de estudios
                                            </Label>
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
                                                <SelectTrigger id="programa_estudio_id">
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
                                            <InputError
                                                message={
                                                    errors.programa_estudio_id
                                                }
                                            />
                                        </div>
                                    )}

                                    {!assistant && role !== 'estudiante' && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="cargo_institucional_id">
                                                Cargo institucional
                                            </Label>
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
                                                <SelectTrigger id="cargo_institucional_id">
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
                                            <InputError
                                                message={
                                                    errors.cargo_institucional_id
                                                }
                                            />
                                        </div>
                                    )}

                                    {role === 'estudiante' && (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="codigo_estudiante">
                                                    Código de estudiante
                                                </Label>
                                                <Input
                                                    id="codigo_estudiante"
                                                    name="codigo_estudiante"
                                                    defaultValue={
                                                        user?.codigo_estudiante ??
                                                        ''
                                                    }
                                                    maxLength={40}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.codigo_estudiante
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="condicion_academica">
                                                    Condición académica
                                                </Label>
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
                                                    <SelectTrigger id="condicion_academica">
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
                                                <InputError
                                                    message={
                                                        errors.condicion_academica
                                                    }
                                                />
                                            </div>
                                            {condition === 'Estudiante' ? (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="ciclo_actual">
                                                        Ciclo actual
                                                    </Label>
                                                    <Input
                                                        id="ciclo_actual"
                                                        name="ciclo_actual"
                                                        type="number"
                                                        min={1}
                                                        max={10}
                                                        defaultValue={
                                                            user?.ciclo_actual ??
                                                            ''
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.ciclo_actual
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="anio_egreso">
                                                        Año de egreso
                                                    </Label>
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
                                                    <InputError
                                                        message={
                                                            errors.anio_egreso
                                                        }
                                                    />
                                                </div>
                                            )}
                                            <div className="grid gap-2">
                                                <Label htmlFor="direccion_residencia">
                                                    Dirección (opcional)
                                                </Label>
                                                <Input
                                                    id="direccion_residencia"
                                                    name="direccion_residencia"
                                                    defaultValue={
                                                        user?.direccion_residencia ??
                                                        ''
                                                    }
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={
                                                        errors.direccion_residencia
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {role === 'docente' && (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="codigo_docente">
                                                    Código docente (opcional)
                                                </Label>
                                                <Input
                                                    id="codigo_docente"
                                                    name="codigo_docente"
                                                    defaultValue={
                                                        user?.codigo_docente ??
                                                        ''
                                                    }
                                                    maxLength={40}
                                                />
                                                <InputError
                                                    message={
                                                        errors.codigo_docente
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="especialidad">
                                                    Especialidad (opcional)
                                                </Label>
                                                <Input
                                                    id="especialidad"
                                                    name="especialidad"
                                                    defaultValue={
                                                        user?.especialidad ?? ''
                                                    }
                                                    maxLength={160}
                                                />
                                                <InputError
                                                    message={
                                                        errors.especialidad
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="condicion_laboral">
                                                    Condición laboral (opcional)
                                                </Label>
                                                <Input
                                                    id="condicion_laboral"
                                                    name="condicion_laboral"
                                                    defaultValue={
                                                        user?.condicion_laboral ??
                                                        ''
                                                    }
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.condicion_laboral
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {!user && (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="password">
                                                    Contraseña temporal
                                                </Label>
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
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="password_confirmation">
                                                    Confirmar contraseña
                                                </Label>
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
                                                <InputError
                                                    message={
                                                        errors.password_confirmation
                                                    }
                                                />
                                            </div>
                                            {!assistant && (
                                                <div className="flex items-center gap-2 sm:col-span-2">
                                                    <Checkbox
                                                        id="activar_inmediatamente"
                                                        name="activar_inmediatamente"
                                                        value="1"
                                                        defaultChecked
                                                    />
                                                    <Label htmlFor="activar_inmediatamente">
                                                        Crear activa y
                                                        verificada
                                                        administrativamente
                                                    </Label>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {role === 'administrador' &&
                                        user?.rol !== 'administrador' && (
                                            <div className="grid gap-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="confirmar_administrador"
                                                        name="confirmar_administrador"
                                                        value="1"
                                                        required
                                                    />
                                                    <Label htmlFor="confirmar_administrador">
                                                        Confirmo la asignación
                                                        del rol Administrador
                                                    </Label>
                                                </div>
                                                <InputError
                                                    message={
                                                        errors.confirmar_administrador
                                                    }
                                                />
                                            </div>
                                        )}
                                </CardContent>
                                <CardFooter className="flex gap-2">
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
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
            </main>
        </>
    );
}
