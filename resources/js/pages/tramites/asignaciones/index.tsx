import { Form, Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Search, UserRoundCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteBorradorController from '@/actions/App/Http/Controllers/TramiteBorradorController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type AssignmentRow = {
    id: number;
    codigo: string;
    asunto: string;
    persona_nombre: string;
    fecha_recepcion: string;
    estado: string;
    estado_label: string;
    borrador: string | null;
    documentos_count: number;
    asignacion: {
        destino: string;
        revisor: string;
        fecha_esperada: string | null;
    } | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    tramites: Paginated<AssignmentRow>;
    filters: { q: string; estado: string; destino: string };
    resumen: {
        pendientes: number;
        asignados_hoy: number;
        docentes: number;
        oficina: number;
        reasignados: number;
        sin_revisor: number;
    };
};

export default function AsignacionesIndex({
    tramites,
    filters,
    resumen,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [estado, setEstado] = useState(filters.estado);
    const [destino, setDestino] = useState(filters.destino);

    function filtrar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            TramiteAsignacionController.index(),
            {
                q: query || undefined,
                estado: estado || undefined,
                destino: destino || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Asignación de revisores" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        Gestión documentaria
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Asignación de revisores
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Elige un Docente o la Oficina para revisar cada borrador
                        preparado.
                    </p>
                </header>

                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6"
                    aria-label="Resumen de asignaciones"
                >
                    <SummaryCard
                        title="Pendientes de asignar"
                        value={resumen.pendientes}
                    />
                    <SummaryCard
                        title="Asignados hoy"
                        value={resumen.asignados_hoy}
                    />
                    <SummaryCard
                        title="Asignaciones a Docentes"
                        value={resumen.docentes}
                    />
                    <SummaryCard
                        title="Asignaciones a Oficina"
                        value={resumen.oficina}
                    />
                    <SummaryCard
                        title="Reasignados"
                        value={resumen.reasignados}
                    />
                    <SummaryCard
                        title="Sin revisor"
                        value={resumen.sin_revisor}
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Expedientes en asignación</CardTitle>
                        <CardDescription>
                            La carga activa aparece junto a cada revisor al
                            iniciar o cambiar una asignación.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <form
                            className="grid gap-3 md:grid-cols-[minmax(14rem,1fr)_12rem_12rem_auto]"
                            onSubmit={filtrar}
                        >
                            <Input
                                aria-label="Buscar trámite"
                                placeholder="Código, asunto o persona"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                            <select
                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                aria-label="Estado"
                                value={estado}
                                onChange={(event) =>
                                    setEstado(event.target.value)
                                }
                            >
                                <option value="">Todos los estados</option>
                                <option value="borrador_preparado">
                                    Borrador preparado
                                </option>
                                <option value="pendiente_asignacion">
                                    Pendiente de asignación
                                </option>
                                <option value="asignado">Asignado</option>
                            </select>
                            <select
                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                aria-label="Destino"
                                value={destino}
                                onChange={(event) =>
                                    setDestino(event.target.value)
                                }
                            >
                                <option value="">Todos los destinos</option>
                                <option value="docente">Docente</option>
                                <option value="oficina">Oficina</option>
                            </select>
                            <Button type="submit" variant="outline">
                                <Search /> Filtrar
                            </Button>
                        </form>

                        {tramites.data.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No hay expedientes para los filtros
                                seleccionados.
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {tramites.data.map((tramite) => (
                                    <article
                                        key={tramite.id}
                                        className="grid gap-4 rounded-xl border p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                                    >
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    className="font-semibold underline-offset-4 hover:underline"
                                                    href={TramiteController.show(
                                                        { tramite: tramite.id },
                                                    )}
                                                >
                                                    {tramite.codigo}
                                                </Link>
                                                <TramiteStatusBadge
                                                    estado={tramite.estado}
                                                    label={tramite.estado_label}
                                                />
                                            </div>
                                            <p className="font-medium">
                                                {tramite.asunto}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {tramite.persona_nombre} ·{' '}
                                                {formatDate(
                                                    tramite.fecha_recepcion,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {tramite.borrador ??
                                                    'Sin plantilla'}{' '}
                                                · {tramite.documentos_count}{' '}
                                                archivos
                                            </p>
                                            {tramite.asignacion && (
                                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <UserRoundCheck className="size-4" />
                                                    {tramite.asignacion
                                                        .destino === 'docente'
                                                        ? 'Docente'
                                                        : 'Oficina'}
                                                    :{' '}
                                                    {tramite.asignacion.revisor}
                                                    {tramite.asignacion
                                                        .fecha_esperada
                                                        ? ` · vence ${formatDate(tramite.asignacion.fecha_esperada)}`
                                                        : ''}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {tramite.estado ===
                                                'borrador_preparado' && (
                                                <Form
                                                    {...TramiteBorradorController.prepareAssignment.form(
                                                        { tramite: tramite.id },
                                                    )}
                                                >
                                                    <Button type="submit">
                                                        <ClipboardCheck />{' '}
                                                        Preparar asignación
                                                    </Button>
                                                </Form>
                                            )}
                                            {tramite.estado ===
                                                'pendiente_asignacion' && (
                                                <Button
                                                    render={
                                                        <Link
                                                            href={TramiteAsignacionController.create(
                                                                {
                                                                    tramite:
                                                                        tramite.id,
                                                                },
                                                            )}
                                                        />
                                                    }
                                                >
                                                    <ClipboardCheck /> Asignar
                                                    revisor
                                                </Button>
                                            )}
                                            <Button
                                                render={
                                                    <Link
                                                        href={TramiteController.show(
                                                            {
                                                                tramite:
                                                                    tramite.id,
                                                            },
                                                        )}
                                                    />
                                                }
                                                variant="outline"
                                            >
                                                Ver trámite
                                            </Button>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}

                        {tramites.links.length > 0 && (
                            <nav
                                className="flex flex-wrap justify-center gap-2"
                                aria-label="Paginación"
                            >
                                {tramites.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        render={
                                            link.url ? (
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                />
                                            ) : (
                                                <span />
                                            )
                                        }
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                    >
                                        {link.label
                                            .replace(/<[^>]*>/g, '')
                                            .replaceAll('&laquo;', '‹')
                                            .replaceAll('&raquo;', '›')}
                                    </Button>
                                ))}
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
            <CardContent className="space-y-1 p-5">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
            </CardContent>
        </Card>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00Z`));
}

AsignacionesIndex.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Asignaciones', href: TramiteAsignacionController.index() },
    ],
};
