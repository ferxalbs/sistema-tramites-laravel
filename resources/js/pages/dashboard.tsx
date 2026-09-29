import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Bell,
    CheckCircle2,
    ClipboardList,
    Clock,
    Filter,
    FolderKanban,
    RotateCcw,
    Timer,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
        programa: string;
        tipo: string;
        estado: string;
        revisor: string;
        medio: string;
    };
    catalogs: {
        clasificaciones: Record<string, string>;
        programas: Array<{ id: number; nombre: string }>;
        tipos: Record<string, string>;
        estados: Record<string, string>;
        revisores: Array<{ id: number; name: string }>;
        medios: Array<{ id: number; nombre: string }>;
    };
    summary: {
        total: number;
        cerrados: number;
        proximos: number;
        vencidos: number;
        horas_promedio_atencion: number | null;
    };
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
    estudiante: 'Panel del Estudiante / Egresado',
    asistente: 'Panel Operativo',
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
    const { notificationUnreadCount } = usePage<{
        notificationUnreadCount: number;
    }>().props;
    const [current, setCurrent] = useState(filters);

    const classificationOptions = [
        { value: 'todos', label: 'Todas las clasificaciones' },
        ...Object.entries(catalogs.clasificaciones).map(([value, label]) => ({
            value,
            label,
        })),
    ];
    const programOptions = [
        { value: 'todos', label: 'Todos los programas' },
        ...catalogs.programas.map((programa) => ({
            value: String(programa.id),
            label: programa.nombre,
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

    const hasActiveFilters = Boolean(
        current.desde ||
            current.hasta ||
            current.clasificacion ||
            current.programa ||
            current.tipo ||
            current.estado ||
            current.revisor ||
            current.medio,
    );

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            dashboard(),
            {
                desde: current.desde || undefined,
                hasta: current.hasta || undefined,
                clasificacion: current.clasificacion || undefined,
                programa: current.programa || undefined,
                tipo: current.tipo || undefined,
                estado: current.estado || undefined,
                revisor: current.revisor || undefined,
                medio: current.medio || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function resetFilters() {
        const cleared = {
            desde: '',
            hasta: '',
            clasificacion: '',
            programa: '',
            tipo: '',
            estado: '',
            revisor: '',
            medio: '',
        };
        setCurrent(cleared);
        router.get(dashboard(), {}, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={titles[role]} />
            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            {titles[role]}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Expedientes, indicadores de gestión y actividad en tiempo real.
                        </p>
                    </div>
                    <Button variant="outline" render={<Link href={listHref} />}>
                        <ClipboardList data-icon="inline-start" />
                        Ver expedientes
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </header>

                {/* Filters Section */}
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-base font-semibold">
                            Filtrar indicadores
                        </CardTitle>
                        <CardDescription>
                            Ajuste el rango de fechas y parámetros para segmentar las estadísticas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitFilters} className="flex flex-col gap-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="filter-desde">Desde</Label>
                                    <Input
                                        id="filter-desde"
                                        type="date"
                                        value={current.desde}
                                        onChange={(event) =>
                                            setCurrent({
                                                ...current,
                                                desde: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="filter-hasta">Hasta</Label>
                                    <Input
                                        id="filter-hasta"
                                        type="date"
                                        value={current.hasta}
                                        onChange={(event) =>
                                            setCurrent({
                                                ...current,
                                                hasta: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <DashboardSelect
                                    label="Clasificación"
                                    value={current.clasificacion}
                                    options={classificationOptions}
                                    onChange={(clasificacion) =>
                                        setCurrent({ ...current, clasificacion })
                                    }
                                />
                                <DashboardSelect
                                    label="Programa"
                                    value={current.programa}
                                    options={programOptions}
                                    onChange={(programa) =>
                                        setCurrent({ ...current, programa })
                                    }
                                />
                                <DashboardSelect
                                    label="Estado"
                                    value={current.estado}
                                    options={stateOptions}
                                    onChange={(estado) =>
                                        setCurrent({ ...current, estado })
                                    }
                                />
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
                            </div>

                            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border/50 pt-3">
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={resetFilters}
                                    >
                                        <RotateCcw data-icon="inline-start" />
                                        Limpiar filtros
                                    </Button>
                                )}
                                <Button type="submit">
                                    <Filter data-icon="inline-start" />
                                    Aplicar filtros
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Metrics Section */}
                <section
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    aria-label="Indicadores de expedientes"
                >
                    <Metric
                        label="Expedientes"
                        value={summary.total}
                        icon={FolderKanban}
                        description="Total registrados en el periodo"
                    />
                    <Metric
                        label="En trámite"
                        value={summary.total - summary.cerrados}
                        icon={Clock}
                        description="Expedientes en atención activa"
                    />
                    <Metric
                        label="Cerrados"
                        value={summary.cerrados}
                        icon={CheckCircle2}
                        description="Finalizados o atendidos"
                    />
                    <Metric
                        label="Por vencer"
                        value={summary.proximos}
                        icon={AlertCircle}
                        description="Plazos próximos a vencer"
                    />
                    <Metric
                        label="Vencidos"
                        value={summary.vencidos}
                        icon={AlertTriangle}
                        description="Excedieron plazo estimado"
                    />
                    <Metric
                        label="Tiempo medio"
                        value={
                            summary.horas_promedio_atencion === null
                                ? '—'
                                : `${summary.horas_promedio_atencion} h`
                        }
                        icon={Timer}
                        description="Promedio de horas de atención"
                    />
                    <Metric
                        label="Notificaciones"
                        value={notificationUnreadCount}
                        icon={Bell}
                        description="Mensajes sin leer"
                    />
                </section>

                <p className="text-xs text-muted-foreground">
                    Los indicadores de vencimiento corresponden a plazos estimados referenciales conforme a normativa.
                </p>

                {/* State Distribution & Recent Activity */}
                <div className="grid gap-6 lg:grid-cols-2">
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
                            <CardDescription>
                                Últimos movimientos y actualizaciones en expedientes
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {activity.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    No hay actividad reciente disponible para los filtros aplicados.
                                </p>
                            ) : (
                                <div className="flex flex-col divide-y divide-border/60">
                                    {activity.map((item, index) => (
                                        <div
                                            key={`${item.tramite_id}-${item.fecha}-${index}`}
                                            className="flex flex-col justify-between gap-2 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center"
                                        >
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {item.linkable ? (
                                                        <Link
                                                            className="text-sm font-semibold text-primary hover:underline"
                                                            href={activityHref(
                                                                role,
                                                                item.tramite_id,
                                                            )}
                                                        >
                                                            {item.codigo}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-sm font-semibold text-foreground">
                                                            {item.codigo}
                                                        </span>
                                                    )}
                                                    <span className="max-w-xs truncate text-xs text-muted-foreground">
                                                        • {item.asunto}
                                                    </span>
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {item.title}
                                                </span>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2 self-start sm:self-center">
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs font-normal"
                                                >
                                                    {item.estado}
                                                </Badge>
                                                {item.fecha && (
                                                    <time
                                                        className="whitespace-nowrap text-xs text-muted-foreground"
                                                        dateTime={item.fecha}
                                                    >
                                                        {dateTimeFormatter.format(
                                                            new Date(item.fecha),
                                                        )}
                                                    </time>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Administrator Analytics Section */}
                {role === 'administrador' && adminCharts && (
                    <section
                        className="grid gap-6 lg:grid-cols-2"
                        aria-label="Indicadores de administración"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Registrados y cerrados por mes
                                </CardTitle>
                                <CardDescription>
                                    Evolución mensual de flujo de trámites
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col divide-y divide-border/60">
                                    {adminCharts.monthly.map((month) => (
                                        <div
                                            key={month.periodo}
                                            className="flex items-center justify-between gap-4 py-3 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="font-medium text-foreground">
                                                {month.periodo}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="tabular-nums">
                                                    {month.registrados} reg.
                                                </Badge>
                                                <Badge variant="secondary" className="tabular-nums">
                                                    {month.cerrados} cerr.
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
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
            </div>
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

function Metric({
    label,
    value,
    icon: Icon,
    description,
}: {
    label: string;
    value: number | string;
    icon: React.ComponentType<{ className?: string }>;
    description?: string;
}) {
    return (
        <Card className="transition-all hover:border-foreground/20">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardDescription className="text-sm font-medium">
                    {label}
                </CardDescription>
                <div className="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <Icon className="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                    {value}
                </div>
                {description && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
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
        <div className="flex flex-col gap-2">
            <Label>{label}</Label>
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
                <CardDescription>
                    Distribución cuantitativa por categoría
                </CardDescription>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Sin datos para los filtros seleccionados.
                    </p>
                ) : (
                    <div className="flex flex-col gap-4">
                        {items.map((item, index) => (
                            <div
                                key={`${item.nombre}-${index}`}
                                className="flex flex-col gap-1.5"
                            >
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium text-foreground">
                                        {item.nombre}
                                    </span>
                                    <span className="tabular-nums font-semibold text-muted-foreground">
                                        {item.total}
                                    </span>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary transition-all duration-300"
                                        style={{
                                            width: `${Math.min(100, Math.max(0, (item.total / max) * 100))}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
