import { Head, Link, router } from '@inertiajs/react';
import { FilePlus2, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    SelectGroup,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type TramiteRow = {
    id: number;
    codigo: string;
    clasificacion: string;
    tipo_documento: string;
    persona_nombre: string | null;
    asunto: string;
    fecha_recepcion: string;
    estado: string;
    estado_label: string;
    documentos_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};

type Props = {
    tramites: Paginated<TramiteRow>;
    estados: Record<string, string>;
    filters: {
        q: string;
        estado: string;
    };
    resumen: {
        total: number;
        recibidos: number;
        digitalizados: number;
    };
};

export default function TramitesIndex({
    tramites,
    filters,
    estados,
    resumen,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [estado, setEstado] = useState(filters.estado || 'todos');
    const previous = tramites.links[0];
    const next = tramites.links[tramites.links.length - 1];
    const statusOptions = [
        { value: 'todos', label: 'Todos los estados' },
        ...Object.entries(estados).map(([value, label]) => ({ value, label })),
    ];

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            TramiteController.index(),
            { q: query, estado: estado === 'todos' ? undefined : estado },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Bandeja de trámites" />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Gestión documentaria
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Bandeja de trámites
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Consulta ingresos, digitalizaciones y datos de
                            recepción.
                        </p>
                    </div>
                    <Button render={<Link href={TramiteController.create()} />}>
                        <FilePlus2 />
                        Registrar trámite
                    </Button>
                </header>

                <section
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label="Resumen de trámites"
                >
                    <SummaryCard
                        title="Total registrados"
                        value={resumen.total}
                    />
                    <SummaryCard
                        title="Recibidos en oficina"
                        value={resumen.recibidos}
                    />
                    <SummaryCard
                        title="Digitalizados"
                        value={resumen.digitalizados}
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Buscar y filtrar</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 md:grid-cols-[minmax(16rem,1fr)_16rem_auto]"
                            onSubmit={submitFilters}
                        >
                            <label className="sr-only" htmlFor="buscar-tramite">
                                Buscar trámite
                            </label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="buscar-tramite"
                                    className="pl-9"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Código, referencia física, persona o sumilla"
                                />
                            </div>

                            <Select
                                items={statusOptions}
                                value={estado}
                                onValueChange={(value) =>
                                    setEstado(value ?? 'todos')
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filtrar por estado"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Todos los estados" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {statusOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <Button type="submit" variant="outline">
                                Aplicar filtros
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Registros</CardTitle>
                        <span className="text-sm text-muted-foreground">
                            Página {tramites.current_page} de{' '}
                            {tramites.last_page}
                        </span>
                    </CardHeader>
                    <CardContent>
                        {tramites.data.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-10 text-center">
                                <p className="font-medium">
                                    No hay trámites para mostrar
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Cambia los filtros o registra un nuevo
                                    trámite.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[48rem] text-left text-sm">
                                    <thead className="border-b text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-3 font-medium">
                                                Código interno
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Persona solicitante
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Resumen / sumilla
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Tipo
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Recepción
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Estado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {tramites.data.map((tramite) => (
                                            <tr
                                                key={tramite.id}
                                                className="hover:bg-muted/40"
                                            >
                                                <td className="px-3 py-3 font-medium">
                                                    <Link
                                                        className="text-primary underline-offset-4 hover:underline"
                                                        href={TramiteController.show(
                                                            {
                                                                tramite:
                                                                    tramite.id,
                                                            },
                                                        )}
                                                    >
                                                        {tramite.codigo}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {tramite.persona_nombre ??
                                                        'Documento institucional'}
                                                </td>
                                                <td className="max-w-72 truncate px-3 py-3">
                                                    {tramite.asunto}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {tramite.tipo_documento}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {formatDate(
                                                        tramite.fecha_recepcion,
                                                    )}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <TramiteStatusBadge
                                                        estado={tramite.estado}
                                                        label={
                                                            tramite.estado_label
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {tramites.data.length > 0 && (
                            <nav
                                className="mt-5 flex items-center justify-between border-t pt-4"
                                aria-label="Paginación"
                            >
                                {previous?.url ? (
                                    <Button
                                        render={
                                            <Link
                                                href={previous.url}
                                                preserveScroll
                                            />
                                        }
                                        variant="outline"
                                        size="sm"
                                    >
                                        Anterior
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                {next?.url ? (
                                    <Button
                                        render={
                                            <Link
                                                href={next.url}
                                                preserveScroll
                                            />
                                        }
                                        variant="outline"
                                        size="sm"
                                    >
                                        Siguiente
                                    </Button>
                                ) : (
                                    <span />
                                )}
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function SummaryCard({ title, value }: { title: string; value: number }) {
    return (
        <Card>
            <CardContent className="pt-5">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium' }).format(
        new Date(`${value.slice(0, 10)}T12:00:00`),
    );
}

TramitesIndex.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
    ],
};
