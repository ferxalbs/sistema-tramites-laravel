import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, ClipboardList } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type Role = 'estudiante' | 'asistente' | 'docente' | 'administrador';
type StateCount = { codigo: string; nombre: string; total: number };
type Activity = {
    tramite_id: number;
    codigo: string;
    asunto: string;
    title: string;
    estado: string;
    fecha: string | null;
    linkable: boolean;
};
type ChartItem = { nombre: string; total: number };
type MonthlyItem = { periodo: string; registrados: number; cerrados: number };

type Props = {
    role: Role;
    filters: {
        desde: string;
        hasta: string;
        clasificacion: string;
        tipo: string;
        estado: string;
        revisor: string;
        medio: string;
    };
    catalogs: {
        clasificaciones: Record<string, string>;
        tipos: Record<string, string>;
        estados: Record<string, string>;
        revisores: Array<{ id: number; name: string }>;
        medios: Array<{ id: number; nombre: string }>;
    };
    summary: { total: number; cerrados: number };
    states: StateCount[];
    activity: Activity[];
    adminCharts: {
        monthly: MonthlyItem[];
        types: ChartItem[];
        load: ChartItem[];
        users: ChartItem[];
    } | null;
};

const titles: Record<Role, string> = {
    estudiante: 'Panel del Estudiante/Egresado',
    asistente: 'Panel operativo',
    docente: 'Panel del Docente',
    administrador: 'Panel de Administración',
};

const dateTimeFormatter = new Intl.DateTimeFormat('es-PE', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function Dashboard({
    role,
    filters,
    catalogs,
    summary,
    states,
    activity,
    adminCharts,
}: Props) {
    const [current, setCurrent] = useState(filters);
    const classificationOptions = [
        { value: 'todos', label: 'Todas las clasificaciones' },
        ...Object.entries(catalogs.clasificaciones).map(([value, label]) => ({
            value,
            label,
        })),
    ];
    const stateOptions = [
        { value: 'todos', label: 'Todos los estados' },
        ...Object.entries(catalogs.estados).map(([value, label]) => ({
            value,
            label,
        })),
    ];
    const typeOptions = [
        { value: 'todos', label: 'Todos los tipos' },
        ...Object.entries(catalogs.tipos).map(([value, label]) => ({
            value,
            label,
        })),
    ];
    const reviewerOptions = [
        { value: 'todos', label: 'Todos los revisores' },
        ...catalogs.revisores.map((reviewer) => ({
            value: String(reviewer.id),
            label: reviewer.name,
        })),
    ];
    const deliveryOptions = [
        { value: 'todos', label: 'Todos los medios' },
        ...catalogs.medios.map((medio) => ({
            value: String(medio.id),
            label: medio.nombre,
        })),
    ];
    const listHref =
        role === 'estudiante'
            ? TramiteEstudianteController.index()
            : role === 'docente'
              ? TramiteAsignacionController.docenteIndex()
              : TramiteController.index();

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            dashboard(),
            {
                desde: current.desde || undefined,
                hasta: current.hasta || undefined,
                clasificacion: current.clasificacion || undefined,
                tipo: current.tipo || undefined,
                estado: current.estado || undefined,
                revisor: current.revisor || undefined,
                medio: current.medio || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title={titles[role]} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {titles[role]}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Expedientes y actividad visibles para su rol.
                        </p>
                    </div>
                    <Button variant="outline" render={<Link href={listHref} />}>
                        <ClipboardList data-icon="inline-start" /> Ver
                        expedientes
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtrar indicadores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]"
                        >
                            <label className="grid gap-1 text-sm">
                                Desde
                                <Input
                                    type="date"
                                    value={current.desde}
                                    onChange={(event) =>
                                        setCurrent({
                                            ...current,
                                            desde: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Hasta
                                <Input
                                    type="date"
                                    value={current.hasta}
                                    onChange={(event) =>
                                        setCurrent({
                                            ...current,
                                            hasta: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <div className="grid gap-1 text-sm">
                                <span>Clasificación</span>
                                <Select
                                    items={classificationOptions}
                                    value={current.clasificacion || 'todos'}
                                    onValueChange={(value) =>
                                        setCurrent({
                                            ...current,
                                            clasificacion:
                                                value === 'todos'
                                                    ? ''
                                                    : (value ?? ''),
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Filtrar clasificación"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {classificationOptions.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1 text-sm">
                                <span>Estado</span>
                                <Select
                                    items={stateOptions}
                                    value={current.estado || 'todos'}
                                    onValueChange={(value) =>
                                        setCurrent({
                                            ...current,
                                            estado:
                                                value === 'todos'
                                                    ? ''
                                                    : (value ?? ''),
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Filtrar estado"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Todos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {stateOptions.map((option) => (
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
                            </div>
                            <DashboardSelect
                                label="Tipo de trámite"
                                value={current.tipo}
                                options={typeOptions}
                                onChange={(tipo) =>
                                    setCurrent({ ...current, tipo })
                                }
                            />
                            {catalogs.revisores.length > 0 && (
                                <DashboardSelect
                                    label="Revisor activo"
                                    value={current.revisor}
                                    options={reviewerOptions}
                                    onChange={(revisor) =>
                                        setCurrent({ ...current, revisor })
                                    }
                                />
                            )}
                            <DashboardSelect
                                label="Medio de entrega"
                                value={current.medio}
                                options={deliveryOptions}
                                onChange={(medio) =>
                                    setCurrent({ ...current, medio })
                                }
                            />
                            <Button type="submit" className="self-end">
                                Aplicar
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <section
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label="Indicadores de expedientes"
                >
                    <Metric label="Expedientes" value={summary.total} />
                    <Metric
                        label="En trámite"
                        value={summary.total - summary.cerrados}
                    />
                    <Metric label="Cerrados" value={summary.cerrados} />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <BarList
                        title="Expedientes por estado"
                        items={states.map((state) => ({
                            nombre: state.nombre,
                            total: state.total,
                        }))}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle>Actividad reciente</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {activity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay actividad reciente disponible.
                                </p>
                            ) : (
                                <ol className="divide-y">
                                    {activity.map((item, index) => (
                                        <li
                                            key={`${item.tramite_id}-${item.fecha}-${index}`}
                                            className="py-3 first:pt-0"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                {item.linkable ? (
                                                    <Link
                                                        className="font-medium text-primary hover:underline"
                                                        href={activityHref(
                                                            role,
                                                            item.tramite_id,
                                                        )}
                                                    >
                                                        {item.codigo}
                                                    </Link>
                                                ) : (
                                                    <strong>
                                                        {item.codigo}
                                                    </strong>
                                                )}
                                                {item.fecha && (
                                                    <time
                                                        className="text-xs text-muted-foreground"
                                                        dateTime={item.fecha}
                                                    >
                                                        {dateTimeFormatter.format(
                                                            new Date(
                                                                item.fecha,
                                                            ),
                                                        )}
                                                    </time>
                                                )}
                                            </div>
                                            <p className="text-sm">
                                                {item.title}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {item.asunto} · {item.estado}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {role === 'administrador' && adminCharts && (
                    <section
                        className="grid gap-4 lg:grid-cols-2"
                        aria-label="Indicadores de administración"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Registrados y cerrados por mes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3">
                                    {adminCharts.monthly.map((month) => (
                                        <li
                                            key={month.periodo}
                                            className="flex items-center justify-between gap-4 text-sm"
                                        >
                                            <span>{month.periodo}</span>
                                            <span className="tabular-nums">
                                                {month.registrados} registrados
                                                · {month.cerrados} cerrados
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                        <BarList
                            title="Tipos de trámite"
                            items={adminCharts.types}
                        />
                        <BarList
                            title="Carga activa por revisor"
                            items={adminCharts.load}
                        />
                        <BarList
                            title="Usuarios por mes y rol"
                            items={adminCharts.users}
                        />
                    </section>
                )}
            </main>
        </>
    );
}

function activityHref(role: Role, tramiteId: number) {
    if (role === 'estudiante')
        return TramiteEstudianteController.show({ tramite: tramiteId });
    if (role === 'docente')
        return TramiteAsignacionController.docenteShow({ tramite: tramiteId });
    return TramiteController.show({ tramite: tramiteId });
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-2 text-3xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function DashboardSelect({
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
        <div className="grid gap-1 text-sm">
            <span>{label}</span>
            <Select
                items={options}
                value={value || 'todos'}
                onValueChange={(selected) =>
                    onChange(selected === 'todos' ? '' : (selected ?? ''))
                }
            >
                <SelectTrigger
                    aria-label={`Filtrar ${label.toLowerCase()}`}
                    className="w-full"
                >
                    <SelectValue placeholder="Todos" />
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
        </div>
    );
}

function BarList({ title, items }: { title: string; items: ChartItem[] }) {
    const max = Math.max(1, ...items.map((item) => item.total));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Sin datos para los filtros seleccionados.
                    </p>
                ) : (
                    <ul className="space-y-4">
                        {items.map((item, index) => (
                            <li key={`${item.nombre}-${index}`}>
                                <div className="mb-1 flex justify-between gap-3 text-sm">
                                    <span>{item.nombre}</span>
                                    <strong className="tabular-nums">
                                        {item.total}
                                    </strong>
                                </div>
                                <div className="h-2 rounded-full bg-muted">
                                    <div
                                        className="h-2 rounded-full bg-primary"
                                        style={{
                                            width: `${(item.total / max) * 100}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
