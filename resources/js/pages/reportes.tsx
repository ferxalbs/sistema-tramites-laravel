import { Form, Head, Link } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import TramiteReportController from '@/actions/App/Http/Controllers/TramiteReportController';
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

type ReportRow = {
    codigo: string;
    asunto: string;
    tipo: string;
    programa: string;
    clasificacion: string;
    estado: string;
    revisor: string;
    recepcion: string;
    actualizacion: string;
};

type Filters = {
    desde: string;
    hasta: string;
    programa: string;
    clasificacion: string;
    tipo: string;
    estado: string;
    revisor: string;
    medio: string;
};

type Props = {
    rows: {
        data: ReportRow[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
    catalogs: {
        programas: Array<{ id: number; nombre: string }>;
        clasificaciones: Record<string, string>;
        tipos: Record<string, string>;
        estados: Record<string, string>;
        revisores: Array<{ id: number; name: string }>;
        medios: Array<{ id: number; nombre: string }>;
    };
    canExport: boolean;
};

const columns: Array<{ key: keyof ReportRow; label: string }> = [
    { key: 'codigo', label: 'Código' },
    { key: 'asunto', label: 'Asunto' },
    { key: 'tipo', label: 'Tipo de documento' },
    { key: 'programa', label: 'Programa' },
    { key: 'clasificacion', label: 'Clasificación' },
    { key: 'estado', label: 'Estado' },
    { key: 'revisor', label: 'Revisor' },
    { key: 'recepcion', label: 'Recepción' },
    { key: 'actualizacion', label: 'Actualización' },
];

export default function Reportes({
    rows,
    filters,
    catalogs,
    canExport,
}: Props) {
    return (
        <>
            <Head title="Reportes" />
            <main className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <FileSpreadsheet className="size-6" /> Reportes
                        administrativos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta expedientes por fecha de registro, programa,
                        documento, estado, revisor y medio de entrega.
                    </p>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>Filtros</CardTitle>
                        <CardDescription>
                            El programa corresponde al registrado en la
                            recepción; los expedientes anteriores pueden no
                            tener ese dato.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...TramiteReportController.index.form()}
                            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="report-desde">
                                            Desde
                                        </Label>
                                        <Input
                                            id="report-desde"
                                            name="desde"
                                            type="date"
                                            defaultValue={filters.desde}
                                        />
                                        {errors.desde && (
                                            <p className="text-xs text-destructive">
                                                {errors.desde}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="report-hasta">
                                            Hasta
                                        </Label>
                                        <Input
                                            id="report-hasta"
                                            name="hasta"
                                            type="date"
                                            defaultValue={filters.hasta}
                                        />
                                        {errors.hasta && (
                                            <p className="text-xs text-destructive">
                                                {errors.hasta}
                                            </p>
                                        )}
                                    </div>
                                    <FilterSelect
                                        label="Programa"
                                        name="programa"
                                        value={filters.programa}
                                        options={catalogs.programas.map(
                                            ({ id, nombre }) => [
                                                String(id),
                                                nombre,
                                            ],
                                        )}
                                    />
                                    <FilterSelect
                                        label="Clasificación"
                                        name="clasificacion"
                                        value={filters.clasificacion}
                                        options={Object.entries(
                                            catalogs.clasificaciones,
                                        )}
                                    />
                                    <FilterSelect
                                        label="Tipo de documento"
                                        name="tipo"
                                        value={filters.tipo}
                                        options={Object.entries(catalogs.tipos)}
                                    />
                                    <FilterSelect
                                        label="Estado"
                                        name="estado"
                                        value={filters.estado}
                                        options={Object.entries(
                                            catalogs.estados,
                                        )}
                                    />
                                    <FilterSelect
                                        label="Revisor actual"
                                        name="revisor"
                                        value={filters.revisor}
                                        options={catalogs.revisores.map(
                                            ({ id, name }) => [
                                                String(id),
                                                name,
                                            ],
                                        )}
                                    />
                                    <FilterSelect
                                        label="Medio de entrega"
                                        name="medio"
                                        value={filters.medio}
                                        options={catalogs.medios.map(
                                            ({ id, nombre }) => [
                                                String(id),
                                                nombre,
                                            ],
                                        )}
                                    />
                                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Aplicar filtros
                                        </Button>
                                        <Button
                                            variant="outline"
                                            render={
                                                <Link
                                                    href={TramiteReportController.index()}
                                                />
                                            }
                                        >
                                            Limpiar
                                        </Button>
                                        {canExport && (
                                            <Button
                                                variant="secondary"
                                                render={
                                                    <a
                                                        href={TramiteReportController.export.url(
                                                            { query: filters },
                                                        )}
                                                    />
                                                }
                                            >
                                                Exportar CSV UTF-8
                                            </Button>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>{rows.total} resultados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {rows.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay expedientes para estos filtros.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[70rem] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            {columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    className="p-3 font-medium"
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.data.map((row) => (
                                            <tr
                                                key={row.codigo}
                                                className="border-b align-top"
                                            >
                                                {columns.map((column) => (
                                                    <td
                                                        key={column.key}
                                                        className="p-3"
                                                    >
                                                        {row[column.key] || '—'}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {(rows.prev_page_url || rows.next_page_url) && (
                            <div className="mt-4 flex justify-end gap-2">
                                {rows.prev_page_url && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        render={
                                            <Link href={rows.prev_page_url} />
                                        }
                                    >
                                        Anterior
                                    </Button>
                                )}
                                {rows.next_page_url && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        render={
                                            <Link href={rows.next_page_url} />
                                        }
                                    >
                                        Siguiente
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function FilterSelect({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: keyof Filters;
    value: string;
    options: Array<[string, string]>;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`report-${name}`}>{label}</Label>
            <select
                id={`report-${name}`}
                name={name}
                defaultValue={value}
                className="h-9 rounded-xl border border-input bg-background px-3 text-sm"
            >
                <option value="">Todos</option>
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
        </div>
    );
}
