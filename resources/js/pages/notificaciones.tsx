import { Form, Head, Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index, read, readAll } from '@/routes/notificaciones';

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
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Bell /> Notificaciones
                        </CardTitle>
                        <CardDescription>
                            Avisos internos de los expedientes que puedes
                            consultar.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <nav aria-label="Estado de notificaciones">
                            {[
                                ['todas', 'Todas'],
                                ['no_leidas', 'No leídas'],
                                ['leidas', 'Leídas'],
                            ].map(([value, label]) => (
                                <Button
                                    key={value}
                                    variant={
                                        filters.estado === value
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    render={
                                        <Link
                                            href={target(
                                                value,
                                                filters.prioridad,
                                            )}
                                        />
                                    }
                                >
                                    {label}
                                </Button>
                            ))}
                        </nav>
                        <nav aria-label="Prioridad">
                            {[
                                ['todas', 'Todas las prioridades'],
                                ['normal', 'Normal'],
                                ['alta', 'Alta'],
                                ['urgente', 'Urgente'],
                                ['baja', 'Baja'],
                            ].map(([value, label]) => (
                                <Button
                                    key={value}
                                    variant={
                                        filters.prioridad === value
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    render={
                                        <Link
                                            href={target(filters.estado, value)}
                                        />
                                    }
                                >
                                    {label}
                                </Button>
                            ))}
                        </nav>
                    </CardContent>
                    <CardFooter>
                        <Form {...readAll.form()} disableWhileProcessing>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Marcar todas como leídas
                                </Button>
                            )}
                        </Form>
                    </CardFooter>
                </Card>

                {notifications.data.length === 0 ? (
                    <Card>
                        <CardContent>
                            No hay notificaciones para estos filtros.
                        </CardContent>
                    </Card>
                ) : (
                    notifications.data.map((notification) => (
                        <Card key={notification.id}>
                            <CardHeader>
                                <CardTitle>{notification.titulo}</CardTitle>
                                <CardDescription>
                                    {notification.fecha}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p>{notification.mensaje}</p>
                                <Badge
                                    variant={
                                        notification.leida
                                            ? 'secondary'
                                            : 'default'
                                    }
                                >
                                    {notification.leida ? 'Leída' : 'No leída'}
                                </Badge>
                                {notification.prioridad !== 'normal' && (
                                    <Badge variant="outline">
                                        {notification.prioridad}
                                    </Badge>
                                )}
                            </CardContent>
                            <CardFooter>
                                {notification.url && (
                                    <Button
                                        variant="outline"
                                        render={
                                            <Link href={notification.url} />
                                        }
                                    >
                                        Ver expediente
                                    </Button>
                                )}
                                {!notification.leida && (
                                    <Form
                                        {...read.form(notification.id)}
                                        disableWhileProcessing
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Marcar como leída
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </CardFooter>
                        </Card>
                    ))
                )}

                {notifications.last_page > 1 && (
                    <nav aria-label="Páginas de notificaciones">
                        {notifications.current_page > 1 && (
                            <Button
                                variant="outline"
                                render={
                                    <Link
                                        href={target(
                                            filters.estado,
                                            filters.prioridad,
                                            notifications.current_page - 1,
                                        )}
                                    />
                                }
                            >
                                Anterior
                            </Button>
                        )}
                        <span>
                            Página {notifications.current_page} de{' '}
                            {notifications.last_page}
                        </span>
                        {notifications.current_page <
                            notifications.last_page && (
                            <Button
                                variant="outline"
                                render={
                                    <Link
                                        href={target(
                                            filters.estado,
                                            filters.prioridad,
                                            notifications.current_page + 1,
                                        )}
                                    />
                                }
                            >
                                Siguiente
                            </Button>
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}
