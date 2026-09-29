import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { create, edit, index } from '@/routes/assistant/students';

type Student = {
    id: number;
    nombre: string;
    email: string;
    dni: string | null;
    codigo: string | null;
    programa: string | null;
    condicion: string | null;
    estado: string;
};

type Props = {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { q: string; programa: number; condicion: string; estado: string };
    programas: Array<{ id: number; nombre: string }>;
};

export default function Estudiantes({ students, filters, programas }: Props) {
    const [query, setQuery] = useState(filters.q);
    const [program, setProgram] = useState(String(filters.programa));
    const [condition, setCondition] = useState(filters.condicion);
    const [status, setStatus] = useState(filters.estado);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            index(),
            {
                q: query,
                programa: program,
                condicion: condition,
                estado: status,
            },
            { preserveState: true, replace: true },
        );
    }

    const currentFilters = {
        q: filters.q,
        programa: filters.programa,
        condicion: filters.condicion,
        estado: filters.estado,
    };

    return (
        <>
            <Head title="Estudiantes y egresados" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Estudiantes y egresados
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            El asistente puede crear y editar únicamente cuentas
                            de este rol.
                        </p>
                    </div>
                    <Button render={<Link href={create()} />}>
                        Crear cuenta provisional
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>Buscar estudiantes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="student-query">
                                    Nombre, DNI, correo o código
                                </Label>
                                <Input
                                    id="student-query"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    maxLength={80}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-program">
                                    Programa
                                </Label>
                                <Select
                                    items={[
                                        { value: '0', label: 'Todos' },
                                        ...programas.map((item) => ({
                                            value: String(item.id),
                                            label: item.nombre,
                                        })),
                                    ]}
                                    value={program}
                                    onValueChange={(value) =>
                                        setProgram(value ?? '0')
                                    }
                                >
                                    <SelectTrigger id="student-program">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="0">
                                                Todos
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
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-condition">
                                    Condición
                                </Label>
                                <Select
                                    items={[
                                        { value: 'all', label: 'Todas' },
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
                                        setCondition(value ?? 'all')
                                    }
                                >
                                    <SelectTrigger id="student-condition">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                Todas
                                            </SelectItem>
                                            <SelectItem value="Estudiante">
                                                Estudiante
                                            </SelectItem>
                                            <SelectItem value="Egresado">
                                                Egresado
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-status">Estado</Label>
                                <Select
                                    items={[
                                        { value: 'all', label: 'Todos' },
                                        {
                                            value: 'pendiente',
                                            label: 'Pendiente',
                                        },
                                        { value: 'activo', label: 'Activo' },
                                        {
                                            value: 'inactivo',
                                            label: 'Inactivo',
                                        },
                                        {
                                            value: 'rechazado',
                                            label: 'Rechazado',
                                        },
                                    ]}
                                    value={status}
                                    onValueChange={(value) =>
                                        setStatus(value ?? 'all')
                                    }
                                >
                                    <SelectTrigger id="student-status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                Todos
                                            </SelectItem>
                                            <SelectItem value="pendiente">
                                                Pendiente
                                            </SelectItem>
                                            <SelectItem value="activo">
                                                Activo
                                            </SelectItem>
                                            <SelectItem value="inactivo">
                                                Inactivo
                                            </SelectItem>
                                            <SelectItem value="rechazado">
                                                Rechazado
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit">Buscar</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Resultados ({students.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {students.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No se encontraron cuentas.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[42rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Estudiante/egresado
                                            </th>
                                            <th className="p-3 font-medium">
                                                DNI
                                            </th>
                                            <th className="p-3 font-medium">
                                                Código
                                            </th>
                                            <th className="p-3 font-medium">
                                                Programa
                                            </th>
                                            <th className="p-3 font-medium">
                                                Condición
                                            </th>
                                            <th className="p-3 font-medium">
                                                Estado
                                            </th>
                                            <th className="p-3 font-medium">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {students.data.map((student) => (
                                            <tr key={student.id}>
                                                <td className="p-3">
                                                    <strong>
                                                        {student.nombre}
                                                    </strong>
                                                    <br />
                                                    {student.email}
                                                </td>
                                                <td className="p-3">
                                                    {student.dni ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {student.codigo ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {student.programa ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {student.condicion ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {student.estado}
                                                </td>
                                                <td className="p-3">
                                                    <Link
                                                        href={edit({
                                                            user: student.id,
                                                        })}
                                                        className={buttonVariants(
                                                            {
                                                                variant:
                                                                    'outline',
                                                                size: 'sm',
                                                            },
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {students.last_page > 1 && (
                            <nav
                                className="flex justify-end gap-2 pt-4"
                                aria-label="Paginación de estudiantes"
                            >
                                {students.current_page > 1 && (
                                    <Button
                                        variant="outline"
                                        render={
                                            <Link
                                                href={index({
                                                    query: {
                                                        ...currentFilters,
                                                        page:
                                                            students.current_page -
                                                            1,
                                                    },
                                                })}
                                            />
                                        }
                                    >
                                        Anterior
                                    </Button>
                                )}
                                <span className="self-center text-sm">
                                    Página {students.current_page} de{' '}
                                    {students.last_page}
                                </span>
                                {students.current_page < students.last_page && (
                                    <Button
                                        variant="outline"
                                        render={
                                            <Link
                                                href={index({
                                                    query: {
                                                        ...currentFilters,
                                                        page:
                                                            students.current_page +
                                                            1,
                                                    },
                                                })}
                                            />
                                        }
                                    >
                                        Siguiente
                                    </Button>
                                )}
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
