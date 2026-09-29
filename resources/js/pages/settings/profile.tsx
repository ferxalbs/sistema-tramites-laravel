import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type Identity = {
    name: string;
    email: string;
    rol: 'estudiante' | 'docente' | 'asistente' | 'administrador';
    dni: string | null;
    celular: string | null;
    correo_alternativo: string | null;
    cuenta_provisional: boolean | null;
};

type ProfileData = {
    codigo?: string | null;
    programa?: string | null;
    condicion?: string | null;
    ciclo_actual?: number | null;
    anio_egreso?: number | null;
    direccion_residencia?: string | null;
    especialidad?: string | null;
    condicion_laboral?: string | null;
} | null;

type Props = {
    identity: Identity;
    profile: ProfileData;
    status?: string;
};

const roleLabels: Record<Identity['rol'], string> = {
    estudiante: 'Estudiante / egresado',
    docente: 'Docente',
    asistente: 'Asistente de gestión documentaria',
    administrador: 'Administrador',
};

export default function Profile({ identity, profile, status }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isStudent = identity.rol === 'estudiante';
    const isTeacher = identity.rol === 'docente';

    return (
        <>
            <Head title="Mi perfil" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Mi perfil"
                    description={
                        isStudent || isTeacher
                            ? 'Los datos de identidad y el correo institucional los actualiza la administración.'
                            : 'Los datos de esta cuenta los actualiza la administración.'
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Datos institucionales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Nombre
                                </dt>
                                <dd>{identity.name}</dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Rol
                                </dt>
                                <dd>{roleLabels[identity.rol]}</dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Correo institucional
                                </dt>
                                <dd>{identity.email}</dd>
                            </div>
                            {!isStudent && !isTeacher && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Cuenta provisional
                                    </dt>
                                    <dd>
                                        {identity.cuenta_provisional === null
                                            ? 'No determinado'
                                            : identity.cuenta_provisional
                                              ? 'Sí'
                                              : 'No'}
                                    </dd>
                                </div>
                            )}
                            {identity.dni && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        DNI
                                    </dt>
                                    <dd>{identity.dni}</dd>
                                </div>
                            )}
                            {profile?.codigo && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Código
                                    </dt>
                                    <dd>{profile.codigo}</dd>
                                </div>
                            )}
                            {profile?.programa && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Programa
                                    </dt>
                                    <dd>{profile.programa}</dd>
                                </div>
                            )}
                            {isStudent && profile?.condicion && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Condición académica
                                    </dt>
                                    <dd>{profile.condicion}</dd>
                                </div>
                            )}
                            {isStudent &&
                                (profile?.ciclo_actual ||
                                    profile?.anio_egreso) && (
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            Ciclo / año de egreso
                                        </dt>
                                        <dd>
                                            {profile.ciclo_actual ??
                                                profile.anio_egreso}
                                        </dd>
                                    </div>
                                )}
                        </dl>
                    </CardContent>
                </Card>

                {auth.user.email_verified_at === null && (
                    <p className="text-sm text-muted-foreground">
                        El correo institucional aún no está verificado.{' '}
                        <Link href={send()} as="button" className="underline">
                            Reenviar enlace de verificación
                        </Link>
                        {status === 'verification-link-sent' && (
                            <span> Se envió un nuevo enlace.</span>
                        )}
                    </p>
                )}

                {(isStudent || isTeacher) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {isStudent
                                    ? 'Datos de contacto'
                                    : 'Datos de contacto y profesionales'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ProfileController.update.form()}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="celular">
                                                Celular
                                            </Label>
                                            <Input
                                                id="celular"
                                                name="celular"
                                                defaultValue={
                                                    identity.celular ?? ''
                                                }
                                                required
                                                autoComplete="tel"
                                            />
                                            <InputError
                                                message={errors.celular}
                                            />
                                        </div>
                                        {isStudent && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="correo_alternativo">
                                                        Correo alternativo
                                                    </Label>
                                                    <Input
                                                        id="correo_alternativo"
                                                        name="correo_alternativo"
                                                        type="email"
                                                        defaultValue={
                                                            identity.correo_alternativo ??
                                                            ''
                                                        }
                                                        autoComplete="email"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.correo_alternativo
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="direccion_residencia">
                                                        Dirección de residencia
                                                    </Label>
                                                    <Input
                                                        id="direccion_residencia"
                                                        name="direccion_residencia"
                                                        defaultValue={
                                                            profile?.direccion_residencia ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.direccion_residencia
                                                        }
                                                    />
                                                </div>
                                            </>
                                        )}
                                        {isTeacher && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="especialidad">
                                                        Especialidad
                                                    </Label>
                                                    <Input
                                                        id="especialidad"
                                                        name="especialidad"
                                                        defaultValue={
                                                            profile?.especialidad ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.especialidad
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="condicion_laboral">
                                                        Condición laboral
                                                    </Label>
                                                    <Input
                                                        id="condicion_laboral"
                                                        name="condicion_laboral"
                                                        defaultValue={
                                                            profile?.condicion_laboral ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.condicion_laboral
                                                        }
                                                    />
                                                </div>
                                            </>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar perfil
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Mi perfil', href: edit() }],
};
