import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Save } from 'lucide-react';
import {
    Children,
    cloneElement,
    isValidElement,
    useState,
    type ReactElement,
} from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field as ShadcnField,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type Reviewer = {
    id: number;
    name: string;
    dni: string | null;
    rol: string;
    carga_activa: number;
};
type Props = {
    tramite: {
        id: number;
        codigo: string;
        asunto: string;
        persona_nombre: string | null;
        fecha_recepcion: string;
    };
    modo: 'asignar' | 'reasignar';
    asignacion: {
        id: number;
        destino: string;
        revisor_id: number;
        revisor: string;
    } | null;
    revisores: { docente: Reviewer[]; oficina: Reviewer[] };
    destino_inicial?: string;
    revisor_sugerido_id?: number | null;
};

type FormData = {
    destino: string;
    revisor_id: number | null;
    motivo: string;
    instrucciones_revision: string;
    fecha_esperada: string;
    motivo_reasignacion: string;
};

export default function AsignacionForm({
    tramite,
    modo,
    asignacion,
    revisores,
    destino_inicial,
    revisor_sugerido_id,
}: Props) {
    const [busquedaRevisor, setBusquedaRevisor] = useState('');
    const form = useForm<FormData>({
        destino: asignacion?.destino ?? destino_inicial ?? 'docente',
        revisor_id: asignacion?.revisor_id ?? revisor_sugerido_id ?? null,
        motivo: '',
        instrucciones_revision: '',
        fecha_esperada: '',
        motivo_reasignacion: '',
    });
    const candidatos =
        form.data.destino === 'docente' ? revisores.docente : revisores.oficina;
    const textoBusqueda = busquedaRevisor.trim().toLocaleLowerCase();
    const dniBusqueda = busquedaRevisor.replace(/\D/g, '');
    const candidatosFiltrados = candidatos.filter(
        (revisor) =>
            revisor.id === form.data.revisor_id ||
            revisor.name.toLocaleLowerCase().includes(textoBusqueda) ||
            (dniBusqueda !== '' && (revisor.dni ?? '').includes(dniBusqueda)),
    );
    const endpoint =
        modo === 'asignar'
            ? TramiteAsignacionController.store.url({ tramite: tramite.id })
            : TramiteAsignacionController.update.url({ tramite: tramite.id });

    function enviar(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(endpoint, { preserveScroll: true });
    }

    return (
        <>
            <Head
                title={`${modo === 'asignar' ? 'Asignar' : 'Reasignar'} ${tramite.codigo}`}
            />
            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button
                        render={
                            <Link
                                href={TramiteController.show({
                                    tramite: tramite.id,
                                })}
                            />
                        }
                        variant="outline"
                        size="icon"
                        aria-label="Volver al trámite"
                    >
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {tramite.codigo} ·{' '}
                            {tramite.persona_nombre ??
                                'Documento institucional'}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {modo === 'asignar'
                                ? 'Asignar revisor'
                                : 'Reasignar trámite'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tramite.asunto}
                        </p>
                    </div>
                </header>

                {modo === 'reasignar' && asignacion && (
                    <Alert>
                        <AlertDescription>
                            Asignación actual:{' '}
                            <strong>
                                {asignacion.destino === 'docente'
                                    ? 'Docente'
                                    : 'Oficina'}{' '}
                                · {asignacion.revisor}
                            </strong>
                            . La revisión todavía no ha iniciado.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Datos de la asignación</CardTitle>
                        <CardDescription>
                            El destino determina el rol permitido. La carga
                            muestra cuántos expedientes activos tiene cada
                            revisor.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={enviar}>
                            <FieldGroup className="grid gap-5 md:grid-cols-2">
                                <Field
                                    id="destino"
                                    label="Destino"
                                    error={form.errors.destino}
                                >
                                    <select
                                        id="destino"
                                        className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                        value={form.data.destino}
                                        onChange={(event) => {
                                            form.setData(
                                                'destino',
                                                event.target.value,
                                            );
                                            form.setData('revisor_id', null);
                                            setBusquedaRevisor('');
                                        }}
                                    >
                                        <option value="docente">Docente</option>
                                        <option value="oficina">Oficina</option>
                                    </select>
                                </Field>
                                <FieldGroup className="gap-3">
                                    <Field
                                        id="buscar_revisor"
                                        label="Buscar revisor por nombre o DNI"
                                    >
                                        <Input
                                            id="buscar_revisor"
                                            type="search"
                                            value={busquedaRevisor}
                                            onChange={(event) =>
                                                setBusquedaRevisor(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Escribe el nombre o DNI"
                                            maxLength={80}
                                        />
                                    </Field>
                                    <Field
                                        id="revisor_id"
                                        label="Revisor activo"
                                        error={form.errors.revisor_id}
                                    >
                                        <select
                                            id="revisor_id"
                                            className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                            required
                                            value={form.data.revisor_id ?? ''}
                                            onChange={(event) =>
                                                form.setData(
                                                    'revisor_id',
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                        >
                                            <option value="">
                                                Seleccione un revisor
                                            </option>
                                            {candidatosFiltrados.map(
                                                (revisor) => (
                                                    <option
                                                        key={revisor.id}
                                                        value={revisor.id}
                                                    >
                                                        {revisor.name} · DNI{' '}
                                                        {revisor.dni ??
                                                            'sin registrar'}{' '}
                                                        · {revisor.carga_activa}{' '}
                                                        asignaciones activas
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </Field>
                                    {candidatos.length === 0 ? (
                                        <p className="text-xs text-destructive">
                                            No hay revisores activos disponibles
                                            para este destino.
                                        </p>
                                    ) : candidatosFiltrados.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            No hay revisores con ese nombre o
                                            DNI.
                                        </p>
                                    ) : null}
                                </FieldGroup>
                                {modo === 'reasignar' && (
                                    <Field
                                        id="motivo_reasignacion"
                                        label="Justificación de reasignación"
                                        error={form.errors.motivo_reasignacion}
                                    >
                                        <Textarea
                                            id="motivo_reasignacion"
                                            minLength={8}
                                            maxLength={1000}
                                            required
                                            value={
                                                form.data.motivo_reasignacion
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'motivo_reasignacion',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                <Field
                                    id="motivo"
                                    label={
                                        modo === 'asignar'
                                            ? 'Motivo de asignación'
                                            : 'Motivo para el nuevo revisor'
                                    }
                                    error={form.errors.motivo}
                                >
                                    <Input
                                        id="motivo"
                                        required={modo === 'asignar'}
                                        minLength={
                                            modo === 'asignar' ? 5 : undefined
                                        }
                                        maxLength={255}
                                        value={form.data.motivo}
                                        onChange={(event) =>
                                            form.setData(
                                                'motivo',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Indica brevemente por qué se deriva el trámite"
                                    />
                                </Field>
                                <Field
                                    id="fecha_esperada"
                                    label="Fecha esperada (opcional)"
                                    error={form.errors.fecha_esperada}
                                >
                                    <Input
                                        id="fecha_esperada"
                                        type="date"
                                        value={form.data.fecha_esperada}
                                        onChange={(event) =>
                                            form.setData(
                                                'fecha_esperada',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="instrucciones_revision"
                                    label="Instrucciones para la revisión"
                                    error={form.errors.instrucciones_revision}
                                >
                                    <Textarea
                                        id="instrucciones_revision"
                                        maxLength={2000}
                                        value={form.data.instrucciones_revision}
                                        onChange={(event) =>
                                            form.setData(
                                                'instrucciones_revision',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="flex flex-col-reverse gap-3 sm:flex-row md:col-span-2 md:justify-end">
                                    <Button
                                        render={
                                            <Link
                                                href={TramiteController.show({
                                                    tramite: tramite.id,
                                                })}
                                            />
                                        }
                                        variant="outline"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            candidatos.length === 0
                                        }
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : modo === 'asignar' ? (
                                            <ClipboardCheck />
                                        ) : (
                                            <Save />
                                        )}
                                        {modo === 'asignar'
                                            ? 'Confirmar asignación'
                                            : 'Guardar reasignación'}
                                    </Button>
                                </div>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    const errorId = `${id}-error`;
    const invalid = Boolean(error);
    const control = Children.map(children, (child, index) => {
        if (index !== 0 || !isValidElement(child)) {
            return child;
        }

        return cloneElement(
            child as ReactElement<{
                id?: string;
                'aria-describedby'?: string;
                'aria-invalid'?: boolean;
            }>,
            {
                id,
                'aria-describedby': invalid ? errorId : undefined,
                'aria-invalid': invalid ? true : undefined,
            },
        );
    });

    return (
        <ShadcnField data-invalid={invalid ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {control}
            <FieldError id={errorId}>{error}</FieldError>
        </ShadcnField>
    );
}

AsignacionForm.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Detalle', href: '#' },
        { title: 'Asignación', href: '#' },
    ],
};
