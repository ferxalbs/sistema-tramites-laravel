import { Form, Head, Link } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import TramiteEntregaAdminController from '@/actions/App/Http/Controllers/TramiteEntregaAdminController';
import TramiteEntregaController from '@/actions/App/Http/Controllers/TramiteEntregaController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Expediente = {
    id: number;
    codigo: string;
    asunto: string;
    documento: string;
    estado: string;
    medio: string | null;
    entrega_id: number | null;
    confirmado: boolean | null;
};

type Medio = {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    activo: boolean;
    requiere_evidencia: boolean;
};

type Plantilla = {
    id: number;
    codigo: string;
    version: number;
    nombre: string;
    requiere_firma_fisica: boolean;
    permite_no_firma: boolean;
};

type Props = {
    tramites: {
        data: Expediente[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    medios: Medio[];
    plantillas: Plantilla[];
};

export default function EntregasAdmin({ tramites, medios, plantillas }: Props) {
    useFlashToast();

    return (
        <>
            <Head title="Entregas y cierres" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <PackageCheck className="size-6" /> Entregas y cierres
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta documentos emitidos y administra los medios de
                        entrega y la firma por plantilla.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Expedientes con documento emitido</CardTitle>
                        <CardDescription>
                            La anulación y la reapertura se registran desde el
                            detalle del expediente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tramites.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay documentos emitidos.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[42rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Expediente
                                            </th>
                                            <th className="p-3 font-medium">
                                                Documento
                                            </th>
                                            <th className="p-3 font-medium">
                                                Estado
                                            </th>
                                            <th className="p-3 font-medium">
                                                Medio
                                            </th>
                                            <th className="p-3 font-medium">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tramites.data.map((tramite) => (
                                            <tr
                                                key={tramite.id}
                                                className="border-b align-top"
                                            >
                                                <td className="p-3">
                                                    <span className="font-medium">
                                                        {tramite.codigo}
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {tramite.asunto}
                                                    </span>
                                                </td>
                                                <td className="p-3">
                                                    {tramite.documento}
                                                </td>
                                                <td className="p-3">
                                                    {tramite.estado}
                                                </td>
                                                <td className="p-3">
                                                    {tramite.medio ??
                                                        'Pendiente'}
                                                    {tramite.entrega_id && (
                                                        <span className="block text-muted-foreground">
                                                            {tramite.confirmado
                                                                ? 'Confirmada'
                                                                : 'Sin confirmar'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    <Button
                                                        render={
                                                            <Link
                                                                href={TramiteEntregaController.show(
                                                                    {
                                                                        tramite:
                                                                            tramite.id,
                                                                    },
                                                                )}
                                                            />
                                                        }
                                                        variant="outline"
                                                        size="sm"
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
                        {(tramites.prev_page_url || tramites.next_page_url) && (
                            <div className="mt-4 flex justify-end gap-2">
                                {tramites.prev_page_url && (
                                    <Button
                                        render={
                                            <Link
                                                href={tramites.prev_page_url}
                                            />
                                        }
                                        variant="outline"
                                        size="sm"
                                    >
                                        Anterior
                                    </Button>
                                )}
                                {tramites.next_page_url && (
                                    <Button
                                        render={
                                            <Link
                                                href={tramites.next_page_url}
                                            />
                                        }
                                        variant="outline"
                                        size="sm"
                                    >
                                        Siguiente
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Medios de entrega</CardTitle>
                        <CardDescription>
                            El estado y la exigencia de evidencia se aplican a
                            nuevos registros de entrega.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {medios.map((medio) => (
                            <Form
                                key={medio.id}
                                {...TramiteEntregaAdminController.updateMedium.form(
                                    { medio: medio.id },
                                )}
                                className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(12rem,1fr)_10rem_12rem_auto] sm:items-end"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div>
                                            <p className="font-medium">
                                                {medio.nombre}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {medio.tipo} · {medio.codigo}
                                            </p>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`medio-${medio.id}-activo`}
                                            >
                                                Estado
                                            </Label>
                                            <select
                                                id={`medio-${medio.id}-activo`}
                                                name="activo"
                                                defaultValue={
                                                    medio.activo ? '1' : '0'
                                                }
                                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="1">
                                                    Activo
                                                </option>
                                                <option value="0">
                                                    Inactivo
                                                </option>
                                            </select>
                                            {errors.activo && (
                                                <p className="text-xs text-destructive">
                                                    {errors.activo}
                                                </p>
                                            )}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`medio-${medio.id}-evidencia`}
                                            >
                                                Evidencia
                                            </Label>
                                            <select
                                                id={`medio-${medio.id}-evidencia`}
                                                name="requiere_evidencia"
                                                defaultValue={
                                                    medio.requiere_evidencia
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="1">
                                                    Requerida
                                                </option>
                                                <option value="0">
                                                    Opcional
                                                </option>
                                            </select>
                                            {errors.requiere_evidencia && (
                                                <p className="text-xs text-destructive">
                                                    {errors.requiere_evidencia}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Firma por plantilla activa</CardTitle>
                        <CardDescription>
                            Estos ajustes afectan documentos que se emitan
                            después; los documentos ya emitidos conservan su
                            configuración.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {plantillas.map((plantilla) => (
                            <Form
                                key={plantilla.id}
                                {...TramiteEntregaAdminController.updateTemplate.form(
                                    { plantilla: plantilla.id },
                                )}
                                className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(12rem,1fr)_12rem_12rem_auto] sm:items-end"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div>
                                            <p className="font-medium">
                                                {plantilla.nombre} v
                                                {plantilla.version}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {plantilla.codigo}
                                            </p>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`plantilla-${plantilla.id}-firma`}
                                            >
                                                Firma física
                                            </Label>
                                            <select
                                                id={`plantilla-${plantilla.id}-firma`}
                                                name="requiere_firma_fisica"
                                                defaultValue={
                                                    plantilla.requiere_firma_fisica
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="1">
                                                    Requerida
                                                </option>
                                                <option value="0">
                                                    No requerida
                                                </option>
                                            </select>
                                            {errors.requiere_firma_fisica && (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        errors.requiere_firma_fisica
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`plantilla-${plantilla.id}-omitir`}
                                            >
                                                No aplicable
                                            </Label>
                                            <select
                                                id={`plantilla-${plantilla.id}-omitir`}
                                                name="permite_no_firma"
                                                defaultValue={
                                                    plantilla.permite_no_firma
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="1">
                                                    Permitido
                                                </option>
                                                <option value="0">
                                                    No permitido
                                                </option>
                                            </select>
                                            {errors.permite_no_firma && (
                                                <p className="text-xs text-destructive">
                                                    {errors.permite_no_firma}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ))}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
