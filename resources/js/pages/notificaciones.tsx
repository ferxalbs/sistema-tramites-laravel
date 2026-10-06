import { Form, Head, Link } from '@inertiajs/react';
import {
    Bell,
    CheckCheck,
    Inbox,
    ArrowLeft,
    ArrowRight,
    FileText,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, read, readAll } from '@/routes/notificaciones';
import { cn } from '@/lib/utils';

type Notification = {
    id: number;
    titulo: string;
    mensaje: string;
    prioridad: string;
    leida: boolean;
    fecha: string;
    url: string | null;
};

type Props = {
    notifications: {
        data: Notification[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { estado: string; prioridad: string };
};

export default function Notificaciones({ notifications, filters }: Props) {
    const target = (estado: string, prioridad: string, page?: number) =>
        index({ query: { estado, prioridad, ...(page ? { page } : {}) } });

    return (
        <>
            <Head title="Notificaciones" />
            <main className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Notificaciones
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Avisos internos de los expedientes que puedes
                            consultar.
                        </p>
                    </div>

                    <Form {...readAll.form()} disableWhileProcessing>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing || notifications.total === 0}
                                className="w-full sm:w-auto"
                            >
                                <CheckCheck className="mr-2 h-4 w-4" />
                                Marcar todas como leídas
                            </Button>
                        )}
                    </Form>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <nav
                            aria-label="Estado de notificaciones"
                            className="flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1"
                        >
                            {[
                                ['todas', 'Todas'],
                                ['no_leidas', 'No leídas'],
                                ['leidas', 'Leídas'],
                            ].map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={target(value, filters.prioridad)}
                                    preserveScroll
                                    className={cn(
                                        'inline-flex h-8 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors',
                                        filters.estado === value
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {label}
                                </Link>
                            ))}
                        </nav>

                        <div className="hidden h-8 w-px bg-border sm:block" />

                        <nav
                            aria-label="Prioridad"
                            className="flex flex-wrap items-center gap-1.5"
                        >
                            <span className="mr-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Prioridad:
                            </span>
                            {[
                                ['todas', 'Todas'],
                                ['normal', 'Normal'],
                                ['alta', 'Alta'],
                                ['urgente', 'Urgente'],
                                ['baja', 'Baja'],
                            ].map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={target(filters.estado, value)}
                                    preserveScroll
                                    className={cn(
                                        'inline-flex h-7 items-center justify-center rounded-full px-3 text-xs font-medium transition-colors',
                                        filters.prioridad === value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                    )}
                                >
                                    {label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    {(filters.estado !== 'todas' ||
                        filters.prioridad !== 'todas') && (
                        <Link
                            href={target('todas', 'todas')}
                            preserveScroll
                            className="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            Restablecer filtros
                        </Link>
                    )}
                </div>

                {/* Notifications List */}
                <div className="flex flex-col gap-4">
                    {notifications.data.length === 0 ? (
                        <div className="flex min-h-[380px] select-none flex-col items-center justify-center rounded-xl border border-dashed bg-card/40 p-8 text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                <Inbox className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h2 className="mt-5 text-lg font-semibold tracking-tight">
                                {filters.estado !== 'todas' ||
                                filters.prioridad !== 'todas'
                                    ? 'Sin resultados para estos filtros'
                                    : 'Bandeja limpia'}
                            </h2>
                            <p className="mt-2 max-w-sm text-center text-sm text-muted-foreground">
                                {filters.estado !== 'todas' ||
                                filters.prioridad !== 'todas'
                                    ? 'No tienes notificaciones que coincidan con la combinación de filtros seleccionada.'
                                    : 'No tienes notificaciones registradas en tu cuenta. ¡Todo está al día!'}
                            </p>
                            {(filters.estado !== 'todas' ||
                                filters.prioridad !== 'todas') && (
                                <Link
                                    href={target('todas', 'todas')}
                                    preserveScroll
                                    className="mt-4 inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium shadow-xs hover:bg-accent hover:text-accent-foreground"
                                >
                                    Ver todas las notificaciones
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="flex flex-col divide-y">
                                {notifications.data.map((notification) => (
                                    <div
                                        key={notification.id}
                                        className={cn(
                                            'flex flex-col gap-4 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-start sm:p-6',
                                            !notification.leida
                                                ? 'bg-primary/5'
                                                : '',
                                        )}
                                    >
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border bg-background shadow-sm">
                                            <Bell
                                                className={cn(
                                                    'h-5 w-5',
                                                    !notification.leida
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                            />
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3
                                                    className={cn(
                                                        'text-base font-semibold',
                                                        !notification.leida &&
                                                            'text-primary',
                                                    )}
                                                >
                                                    {notification.titulo}
                                                </h3>
                                                {!notification.leida && (
                                                    <Badge
                                                        variant="default"
                                                        className="h-5 px-1.5 text-[10px] uppercase"
                                                    >
                                                        Nueva
                                                    </Badge>
                                                )}
                                                {notification.prioridad !==
                                                    'normal' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="h-5 px-1.5 text-[10px] uppercase"
                                                    >
                                                        {notification.prioridad}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm leading-relaxed text-muted-foreground">
                                                {notification.mensaje}
                                            </p>
                                            <p className="mt-1 text-xs font-medium text-muted-foreground/80">
                                                {notification.fecha}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end">
                                            {notification.url && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full sm:w-auto"
                                                    render={
                                                        <Link
                                                            href={
                                                                notification.url
                                                            }
                                                        />
                                                    }
                                                >
                                                    <FileText className="mr-2 h-4 w-4" />
                                                    Ver expediente
                                                </Button>
                                            )}
                                            {!notification.leida && (
                                                <Form
                                                    {...read.form(
                                                        notification.id,
                                                    )}
                                                    disableWhileProcessing
                                                    className="w-full sm:w-auto"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                            className="w-full text-muted-foreground hover:text-foreground sm:w-auto"
                                                        >
                                                            Marcar leída
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {notifications.last_page > 1 && (
                    <div className="mt-2 flex items-center justify-between border-t pt-4">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={notifications.current_page === 1}
                            render={
                                notifications.current_page > 1 ? (
                                    <Link
                                        href={target(
                                            filters.estado,
                                            filters.prioridad,
                                            notifications.current_page - 1,
                                        )}
                                    />
                                ) : (
                                    <span />
                                )
                            }
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Anterior
                        </Button>
                        <span className="text-sm font-medium text-muted-foreground">
                            Página {notifications.current_page} de{' '}
                            {notifications.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                notifications.current_page ===
                                notifications.last_page
                            }
                            render={
                                notifications.current_page <
                                notifications.last_page ? (
                                    <Link
                                        href={target(
                                            filters.estado,
                                            filters.prioridad,
                                            notifications.current_page + 1,
                                        )}
                                    />
                                ) : (
                                    <span />
                                )
                            }
                        >
                            Siguiente
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                )}
            </main>
        </>
    );
}
