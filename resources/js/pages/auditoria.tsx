import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type AuditEvent = {
    id: number;
    fecha: string;
    actor: string;
    accion: string;
    modulo: string;
    entidad: string;
    entidad_id: number;
    resultado: string;
};

type Props = {
    events: {
        data: AuditEvent[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function Auditoria({ events }: Props) {
    return (
        <>
            <Head title="Auditoría" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <ShieldCheck className="size-6" /> Auditoría
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Eventos de gestión de cuentas y expedientes. Las
                        contraseñas y los datos privados no se muestran aquí.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>{events.total} eventos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {events.data.length === 0 ? (
                            <p className="py-8 text-sm text-muted-foreground">
                                Todavía no hay eventos registrados.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[44rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Fecha
                                            </th>
                                            <th className="p-3 font-medium">
                                                Actor
                                            </th>
                                            <th className="p-3 font-medium">
                                                Acción
                                            </th>
                                            <th className="p-3 font-medium">
                                                Módulo
                                            </th>
                                            <th className="p-3 font-medium">
                                                Entidad
                                            </th>
                                            <th className="p-3 font-medium">
                                                Resultado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {events.data.map((event) => (
                                            <tr
                                                key={`${event.modulo}-${event.id}`}
                                            >
                                                <td className="p-3 whitespace-nowrap">
                                                    {event.fecha}
                                                </td>
                                                <td className="p-3">
                                                    {event.actor}
                                                </td>
                                                <td className="p-3">
                                                    {event.accion}
                                                </td>
                                                <td className="p-3">
                                                    {event.modulo}
                                                </td>
                                                <td className="p-3 whitespace-nowrap">
                                                    {event.entidad} #
                                                    {event.entidad_id}
                                                </td>
                                                <td className="p-3">
                                                    {event.resultado}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {events.last_page > 1 && (
                            <nav
                                className="mt-4 flex items-center justify-between gap-3"
                                aria-label="Páginas de auditoría"
                            >
                                <Button
                                    variant="outline"
                                    disabled={!events.prev_page_url}
                                    render={
                                        events.prev_page_url ? (
                                            <Link href={events.prev_page_url} />
                                        ) : (
                                            <span />
                                        )
                                    }
                                >
                                    Anterior
                                </Button>
                                <span className="text-sm text-muted-foreground">
                                    Página {events.current_page} de{' '}
                                    {events.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    disabled={!events.next_page_url}
                                    render={
                                        events.next_page_url ? (
                                            <Link href={events.next_page_url} />
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
            </main>
        </>
    );
}
