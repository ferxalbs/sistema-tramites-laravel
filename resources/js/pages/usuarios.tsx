import { Form, Head, Link, router } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    create,
    edit,
    index,
    resetPassword,
    update,
} from '@/routes/admin/users';

type Status = 'activo' | 'pendiente' | 'inactivo' | 'rechazado';
type UserRow = {
    id: number;
    name: string;
    email: string;
    rol: string;
    estado: Status;
    created_at: string | null;
};
type Event = {
    accion: string;
    estado_anterior: Status;
    estado_nuevo: Status;
    motivo: string | null;
    created_at: string;
    actor: string | null;
};
type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    viewerId: number;
    filters: { q: string; rol: string; estado: string };
    counts: Partial<Record<Status, number>>;
    selected:
        | (UserRow & {
              motivo: string | null;
              teacher_request: { cargo: string; motivo: string } | null;
              events: Event[];
          })
        | null;
};

const statusLabels: Record<Status, string> = {
    activo: 'Activo',
    pendiente: 'Pendiente',
    inactivo: 'Inactivo',
    rechazado: 'Rechazado',
};
const roleLabels: Record<string, string> = {
    estudiante: 'Estudiante/Egresado',
    asistente: 'Asistente',
    docente: 'Docente',
    administrador: 'Administrador',
};
const roleOptions = [
    { value: 'todos', label: 'Todos los roles' },
    ...Object.entries(roleLabels).map(([value, label]) => ({ value, label })),
];
const statusOptions = [
    { value: 'todos', label: 'Todos los estados' },
    ...Object.entries(statusLabels).map(([value, label]) => ({ value, label })),
];
const dateFormatter = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium' });

export default function Usuarios({
    users,
    viewerId,
    filters,
    counts,
    selected,
}: Props) {
    useFlashToast();
    const [query, setQuery] = useState(filters.q);
    const [role, setRole] = useState(filters.rol || 'todos');
    const [status, setStatus] = useState(filters.estado || 'todos');

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            index(),
            {
                q: query || undefined,
                rol: role === 'todos' ? undefined : role,
                estado: status === 'todos' ? undefined : status,
            },
            { replace: true, preserveState: true },
        );
    }

    return (
        <>
            <Head title="Gestión de usuarios" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Users className="size-6" /> Gestión de usuarios
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta cuentas, revisa cambios de estado y controla el
                        acceso.
                    </p>
                    <Button
                        nativeButton={false}
                        render={<Link href={create()} />}
                    >
                        Crear usuario
                    </Button>
                </header>

                <section
                    className="grid gap-3 sm:grid-cols-4"
                    aria-label="Estados de cuenta"
                >
                    {(Object.keys(statusLabels) as Status[]).map((state) => (
                        <Card key={state}>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {statusLabels[state]}
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {counts[state] ?? 0}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Buscar y filtrar</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 md:grid-cols-[minmax(12rem,1fr)_12rem_12rem_auto]"
                            onSubmit={applyFilters}
                        >
                            <Input
                                aria-label="Buscar usuario"
                                placeholder="Nombre o correo"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                maxLength={80}
                            />
                            <FilterSelect
                                label="Filtrar rol"
                                value={role}
                                options={roleOptions}
                                onChange={setRole}
                            />
                            <FilterSelect
                                label="Filtrar estado"
                                value={status}
                                options={statusOptions}
                                onChange={setStatus}
                            />
                            <Button type="submit" variant="outline">
                                <Search /> Aplicar
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Cuentas · página {users.current_page} de{' '}
                            {users.last_page}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {users.data.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No hay cuentas para estos filtros.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[42rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Usuario
                                            </th>
                                            <th className="p-3 font-medium">
                                                Rol
                                            </th>
                                            <th className="p-3 font-medium">
                                                Estado
                                            </th>
                                            <th className="p-3 font-medium">
                                                Registro
                                            </th>
                                            <th className="p-3 font-medium">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {users.data.map((user) => (
                                            <tr key={user.id}>
                                                <td className="p-3">
                                                    <strong>{user.name}</strong>
                                                    <span className="block text-muted-foreground">
                                                        {user.email}
                                                    </span>
                                                </td>
                                                <td className="p-3">
                                                    {roleLabels[user.rol] ??
                                                        user.rol}
                                                </td>
                                                <td className="p-3">
                                                    <Badge
                                                        variant={
                                                            user.estado ===
                                                            'activo'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {
                                                            statusLabels[
                                                                user.estado
                                                            ]
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="p-3">
                                                    {user.created_at
                                                        ? dateFormatter.format(
                                                              new Date(
                                                                  `${user.created_at}T12:00:00Z`,
                                                              ),
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="p-3">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        render={
                                                            <Link
                                                                href={index({
                                                                    query: {
                                                                        ...filters,
                                                                        selected:
                                                                            user.id,
                                                                    },
                                                                })}
                                                            />
                                                        }
                                                    >
                                                        Gestionar
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {users.links.length > 2 && (
                            <nav
                                className="mt-4 flex justify-center gap-2"
                                aria-label="Paginación"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!users.links[0].url}
                                    render={
                                        users.links[0].url ? (
                                            <Link href={users.links[0].url} />
                                        ) : (
                                            <span />
                                        )
                                    }
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        !users.links[users.links.length - 1].url
                                    }
                                    render={
                                        users.links[users.links.length - 1]
                                            .url ? (
                                            <Link
                                                href={
                                                    users.links[
                                                        users.links.length - 1
                                                    ].url!
                                                }
                                            />
                                        ) : (
                                            <span />
                                        )
                                    }
                                >
                                    Siguiente
                                </Button>
                            </nav>
                        )}
                    </CardContent>
                </Card>

                {selected && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Estado de {selected.name}</CardTitle>
                            <Button
                                variant="outline"
                                nativeButton={false}
                                render={
                                    <Link href={edit({ user: selected.id })} />
                                }
                            >
                                Editar usuario
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <p className="text-sm text-muted-foreground">
                                {selected.email} · {roleLabels[selected.rol]} ·{' '}
                                {statusLabels[selected.estado]}
                            </p>
                            {selected.motivo && (
                                <p className="text-sm">
                                    Motivo registrado: {selected.motivo}
                                </p>
                            )}
                            {selected.teacher_request && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Solicitud docente</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p>
                                            Cargo:{' '}
                                            {selected.teacher_request.cargo}
                                        </p>
                                        <p>
                                            Motivo:{' '}
                                            {selected.teacher_request.motivo}
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                            {selected.estado === 'activo' && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">
                                        Se enviará un enlace de un solo uso al
                                        correo institucional. No se mostrará ni
                                        cambiará la contraseña aquí.
                                    </p>
                                    <Form
                                        {...resetPassword.form({
                                            user: selected.id,
                                        })}
                                        disableWhileProcessing
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Generar enlace de
                                                    restablecimiento
                                                </Button>
                                                <InputError
                                                    message={errors.reset}
                                                />
                                            </>
                                        )}
                                    </Form>
                                </div>
                            )}
                            <Form
                                {...update.form({ user: selected.id })}
                                resetOnSuccess={['motivo']}
                                disableWhileProcessing
                            >
                                {({ errors, processing }) => (
                                    <div className="space-y-3">
                                        <label
                                            className="grid gap-1 text-sm"
                                            htmlFor="motivo-cuenta"
                                        >
                                            Motivo para desactivar o rechazar
                                            <textarea
                                                id="motivo-cuenta"
                                                name="motivo"
                                                maxLength={500}
                                                rows={3}
                                                className="w-full rounded-xl border border-input bg-background p-3"
                                            />
                                        </label>
                                        <InputError
                                            message={
                                                errors.motivo || errors.accion
                                            }
                                        />
                                        <div className="flex flex-wrap gap-2">
                                            {selected.estado !== 'activo' && (
                                                <Button
                                                    type="submit"
                                                    name="accion"
                                                    value="activate"
                                                    disabled={processing}
                                                >
                                                    Activar
                                                </Button>
                                            )}
                                            {selected.estado === 'activo' &&
                                                selected.id !== viewerId && (
                                                    <Button
                                                        type="submit"
                                                        name="accion"
                                                        value="deactivate"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Desactivar
                                                    </Button>
                                                )}
                                            {selected.estado ===
                                                'pendiente' && (
                                                <Button
                                                    type="submit"
                                                    name="accion"
                                                    value="reject"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    Rechazar
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </Form>
                            <div>
                                <h2 className="mb-2 font-medium">
                                    Cambios recientes
                                </h2>
                                {selected.events.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Sin cambios registrados.
                                    </p>
                                ) : (
                                    <ol className="divide-y text-sm">
                                        {selected.events.map(
                                            (event, position) => (
                                                <li
                                                    key={`${event.created_at}-${event.accion}-${position}`}
                                                    className="py-2"
                                                >
                                                    <strong>
                                                        {event.accion ===
                                                        'create'
                                                            ? 'Cuenta creada'
                                                            : event.accion ===
                                                                'edit'
                                                              ? 'Datos actualizados'
                                                              : event.accion ===
                                                                  'change_role'
                                                                ? 'Rol cambiado'
                                                                : event.accion ===
                                                                    'reset_link'
                                                                  ? 'Enlace de restablecimiento'
                                                                  : `${statusLabels[event.estado_anterior]} → ${statusLabels[event.estado_nuevo]}`}
                                                    </strong>
                                                    <span className="block text-muted-foreground">
                                                        {event.actor ??
                                                            'Sistema'}{' '}
                                                        · {event.created_at}
                                                    </span>
                                                    {event.motivo && (
                                                        <span className="block">
                                                            {event.motivo}
                                                        </span>
                                                    )}
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
}) {
    return (
        <Select
            items={options}
            value={value}
            onValueChange={(next) => onChange(next ?? 'todos')}
        >
            <SelectTrigger aria-label={label} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

Usuarios.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: index() }],
};
