import { Form, Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import InputError from '@/components/input-error';
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
import { useFlashToast } from '@/hooks/use-flash-toast';
import { deactivate, store } from '@/routes/admin/holidays';

type Holiday = {
    id: number;
    fecha: string;
    nombre: string;
    es_demostracion: boolean | number;
};

export default function Feriados({ holidays }: { holidays: Holiday[] }) {
    useFlashToast();

    return (
        <>
            <Head title="Feriados confirmados" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <CalendarDays className="size-6" /> Feriados confirmados
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Registra únicamente fechas confirmadas oficialmente. No
                        se consulta ningún servicio externo.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Registrar o actualizar</CardTitle>
                        <CardDescription>
                            Guardar una fecha existente actualiza su nombre y la
                            reactiva.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            resetOnSuccess
                            disableWhileProcessing
                            className="grid gap-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto] sm:items-end"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="fecha-feriado">
                                            Fecha
                                        </Label>
                                        <Input
                                            id="fecha-feriado"
                                            type="date"
                                            name="fecha"
                                            required
                                            aria-invalid={!!errors.fecha}
                                        />
                                        <InputError message={errors.fecha} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nombre-feriado">
                                            Nombre
                                        </Label>
                                        <Input
                                            id="nombre-feriado"
                                            name="nombre"
                                            maxLength={160}
                                            required
                                            aria-invalid={!!errors.nombre}
                                        />
                                        <InputError message={errors.nombre} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Guardar
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fechas activas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {holidays.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay feriados confirmados activos.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[28rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Fecha
                                            </th>
                                            <th className="p-3 font-medium">
                                                Nombre
                                            </th>
                                            <th className="p-3 font-medium">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {holidays.map((holiday) => (
                                            <tr key={holiday.id}>
                                                <td className="p-3 whitespace-nowrap">
                                                    {holiday.fecha}
                                                </td>
                                                <td className="p-3">
                                                    {holiday.nombre}
                                                    {Boolean(
                                                        holiday.es_demostracion,
                                                    ) && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-2"
                                                        >
                                                            Demostración
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    <Form
                                                        {...deactivate.form({
                                                            holiday: holiday.id,
                                                        })}
                                                        disableWhileProcessing
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Desactivar
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
