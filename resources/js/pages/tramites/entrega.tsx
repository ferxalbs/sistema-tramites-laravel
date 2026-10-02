import { Form, Head, Link } from '@inertiajs/react';
import {
    Children,
    cloneElement,
    isValidElement,
    type ReactElement,
    type ReactNode,
} from 'react';
import {
    ArrowLeft,
    Ban,
    Check,
    Download,
    FileSignature,
    PackageCheck,
    RotateCcw,
    Send,
} from 'lucide-react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEntregaController from '@/actions/App/Http/Controllers/TramiteEntregaController';
import TramiteStatusBadge from '@/components/tramite-status-badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field as ShadcnField,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type EvidenceType = {
    id: number;
    tipo: string;
    nombre_original: string | null;
    sha256: string | null;
    url_descarga: string | null;
};

type Props = {
    tramite: {
        id: number;
        codigo: string;
        asunto: string;
        estado: string;
        estado_label: string;
    };
    documento: {
        id: number;
        numero: string;
        requiere_firma: boolean;
        firma_perfil_incluida: boolean;
        permite_no_firma: boolean;
    } | null;
    firma: {
        no_requiere_firma: boolean;
        fecha_firma: string | null;
        observacion: string | null;
        nombre_original: string | null;
        sha256: string | null;
        url_descarga: string | null;
    } | null;
    entrega: {
        id: number;
        medio: string;
        tipo_medio: string;
        receptor_nombre: string;
        receptor_documento: string | null;
        receptor_tipo: string;
        correo_destino: string | null;
        medio_utilizado: string | null;
        fecha_entrega: string | null;
        confirmado: boolean;
        confirmado_por_estudiante: boolean;
        observaciones: string | null;
        evidencias: EvidenceType[];
    } | null;
    cierre: {
        resumen: string;
        observacion: string | null;
        fecha_cierre: string | null;
        informe: {
            id: number;
            nombre_archivo: string;
            sha256: string;
            numero_paginas: number;
            codigo_verificacion: string;
            url_descarga: string;
        } | null;
    } | null;
    medios: Array<{
        id: number;
        codigo: string;
        nombre: string;
        tipo: string;
        requiere_evidencia: boolean;
    }>;
    fecha_actual: string;
    puede_preparar: boolean;
    puede_registrar_firma: boolean;
    puede_registrar_entrega: boolean;
    puede_cerrar: boolean;
    puede_anular: boolean;
    puede_reabrir: boolean;
};

export default function TramiteEntregaPage({
    tramite,
    documento,
    firma,
    entrega,
    cierre,
    medios,
    fecha_actual,
    puede_preparar,
    puede_registrar_firma,
    puede_registrar_entrega,
    puede_cerrar,
    puede_anular,
    puede_reabrir,
}: Props) {
    return (
        <>
            <Head title={`Entrega y cierre · ${tramite.codigo}`} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
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
                                Entrega y cierre del expediente
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {tramite.codigo}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {tramite.asunto}
                            </p>
                        </div>
                    </div>
                    <TramiteStatusBadge
                        estado={tramite.estado}
                        label={tramite.estado_label}
                    />
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Preparación y firma</CardTitle>
                        <CardDescription>
                            Preparar el documento no registra la entrega ni
                            confirma su recepción.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {documento ? (
                            <div className="rounded-xl border p-4 text-sm">
                                <p className="font-medium">
                                    Documento oficial {documento.numero}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {documento.firma_perfil_incluida
                                        ? 'La firma escaneada del firmante está incluida en el PDF.'
                                        : `Firma física ${documento.requiere_firma ? 'requerida' : 'no requerida'}.`}
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No hay un documento oficial emitido y verificado
                                para este trámite.
                            </p>
                        )}

                        {puede_preparar && (
                            <Form
                                {...TramiteEntregaController.prepare.form({
                                    tramite: tramite.id,
                                })}
                            >
                                {({ errors, processing }) => (
                                    <div className="space-y-3">
                                        <FieldError id="preparar-entrega-error">
                                            {errors.entrega}
                                        </FieldError>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <Send data-icon="inline-start" />
                                            )}
                                            Preparar para entrega
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}

                        {puede_registrar_firma && documento && (
                            <Form
                                {...TramiteEntregaController.registerSignature.form(
                                    { tramite: tramite.id },
                                )}
                            >
                                {({ errors, processing }) => (
                                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="fecha_firma"
                                            label="Fecha y hora de firma"
                                            error={errors.fecha_firma}
                                        >
                                            <Input
                                                id="fecha_firma"
                                                name="fecha_firma"
                                                type="datetime-local"
                                                required={
                                                    !documento.permite_no_firma
                                                }
                                                defaultValue={fecha_actual}
                                            />
                                        </Field>
                                        {documento.permite_no_firma && (
                                            <ShadcnField
                                                orientation="horizontal"
                                                className="sm:col-span-2"
                                            >
                                                <Checkbox
                                                    id="no-requiere-firma"
                                                    name="no_requiere_firma"
                                                    value="1"
                                                />
                                                <FieldLabel
                                                    htmlFor="no-requiere-firma"
                                                    className="items-start leading-5"
                                                >
                                                    Registrar que no se requiere
                                                    firma física.
                                                </FieldLabel>
                                            </ShadcnField>
                                        )}
                                        {!documento.permite_no_firma && (
                                            <Field
                                                id="evidencia-firma"
                                                label="Evidencia de firma (opcional)"
                                                error={errors.evidencia}
                                            >
                                                <Input
                                                    id="evidencia-firma"
                                                    name="evidencia"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    PDF, JPG o PNG; máximo 10
                                                    MB.
                                                </p>
                                            </Field>
                                        )}
                                        {documento.permite_no_firma && (
                                            <Field
                                                id="evidencia-firma-opcional"
                                                label="Evidencia de firma (opcional)"
                                                error={errors.evidencia}
                                            >
                                                <Input
                                                    id="evidencia-firma-opcional"
                                                    name="evidencia"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                                />
                                            </Field>
                                        )}
                                        <Field
                                            id="observacion-firma"
                                            label="Observación"
                                            error={errors.observacion}
                                        >
                                            <Input
                                                id="observacion-firma"
                                                name="observacion"
                                                maxLength={1000}
                                            />
                                        </Field>
                                        <FieldError id="firma-error">
                                            {errors.firma}
                                        </FieldError>
                                        <div className="sm:col-span-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : (
                                                    <FileSignature data-icon="inline-start" />
                                                )}
                                                Registrar firma y continuar
                                            </Button>
                                        </div>
                                    </FieldGroup>
                                )}
                            </Form>
                        )}

                        {firma && (
                            <div className="rounded-xl border bg-muted/20 p-4 text-sm">
                                <p className="font-medium">
                                    {documento?.firma_perfil_incluida
                                        ? 'La firma escaneada del perfil está incluida en el PDF.'
                                        : firma.no_requiere_firma
                                          ? 'La firma física no aplica.'
                                          : 'Firma registrada.'}
                                </p>
                                {firma.fecha_firma && (
                                    <p className="mt-1 text-muted-foreground">
                                        {formatDateTime(firma.fecha_firma)}
                                    </p>
                                )}
                                {firma.observacion && (
                                    <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                                        {firma.observacion}
                                    </p>
                                )}
                                {firma.url_descarga && (
                                    <a
                                        href={firma.url_descarga}
                                        className={`${buttonVariants({ variant: 'outline', size: 'sm' })} mt-3`}
                                    >
                                        <Download data-icon="inline-start" />
                                        {firma.nombre_original ??
                                            'Descargar evidencia de firma'}
                                    </a>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Registro de entrega</CardTitle>
                        <CardDescription>
                            Se guarda el medio, receptor y evidencia. La
                            recepción queda pendiente hasta que se confirme.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {puede_registrar_entrega && medios.length > 0 && (
                            <Form
                                {...TramiteEntregaController.registerDelivery.form(
                                    { tramite: tramite.id },
                                )}
                            >
                                {({ errors, processing }) => (
                                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="medio_entrega_id"
                                            label="Medio de entrega"
                                            error={errors.medio_entrega_id}
                                        >
                                            <select
                                                id="medio_entrega_id"
                                                name="medio_entrega_id"
                                                required
                                                defaultValue={String(
                                                    medios[0].id,
                                                )}
                                                className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                {medios.map((medio) => (
                                                    <option
                                                        key={medio.id}
                                                        value={medio.id}
                                                    >
                                                        {medio.nombre}
                                                        {medio.requiere_evidencia
                                                            ? ' · requiere evidencia'
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        <Field
                                            id="receptor_tipo"
                                            label="Tipo de receptor"
                                            error={errors.receptor_tipo}
                                        >
                                            <select
                                                id="receptor_tipo"
                                                name="receptor_tipo"
                                                required
                                                defaultValue="Estudiante"
                                                className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                {[
                                                    'Estudiante',
                                                    'Egresado',
                                                    'Docente',
                                                    'Autoridad',
                                                    'Representante autorizado',
                                                    'Otro',
                                                ].map((tipo) => (
                                                    <option key={tipo}>
                                                        {tipo}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        <Field
                                            id="receptor_nombre"
                                            label="Nombre del receptor"
                                            error={errors.receptor_nombre}
                                        >
                                            <Input
                                                id="receptor_nombre"
                                                name="receptor_nombre"
                                                required
                                                minLength={3}
                                                maxLength={160}
                                            />
                                        </Field>
                                        <Field
                                            id="receptor_documento"
                                            label="Documento de identidad (opcional)"
                                            error={errors.receptor_documento}
                                        >
                                            <Input
                                                id="receptor_documento"
                                                name="receptor_documento"
                                                maxLength={30}
                                                inputMode="numeric"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                El sistema guarda solo los
                                                últimos tres dígitos visibles.
                                            </p>
                                        </Field>
                                        <Field
                                            id="receptor_relacion"
                                            label="Relación con el interesado"
                                            error={errors.receptor_relacion}
                                        >
                                            <Input
                                                id="receptor_relacion"
                                                name="receptor_relacion"
                                                maxLength={160}
                                            />
                                        </Field>
                                        <Field
                                            id="fecha_entrega"
                                            label="Fecha y hora de entrega"
                                            error={errors.fecha_entrega}
                                        >
                                            <Input
                                                id="fecha_entrega"
                                                name="fecha_entrega"
                                                type="datetime-local"
                                                required
                                                defaultValue={fecha_actual}
                                            />
                                        </Field>
                                        <Field
                                            id="correo_destino"
                                            label="Correo de destino (si es digital)"
                                            error={errors.correo_destino}
                                        >
                                            <Input
                                                id="correo_destino"
                                                name="correo_destino"
                                                type="email"
                                                maxLength={255}
                                            />
                                        </Field>
                                        <Field
                                            id="medio_utilizado"
                                            label="Medio digital u otro (opcional)"
                                            error={errors.medio_utilizado}
                                        >
                                            <Input
                                                id="medio_utilizado"
                                                name="medio_utilizado"
                                                maxLength={255}
                                            />
                                        </Field>
                                        <Field
                                            id="tipo_evidencia"
                                            label="Tipo de evidencia"
                                            error={errors.tipo_evidencia}
                                        >
                                            <select
                                                id="tipo_evidencia"
                                                name="tipo_evidencia"
                                                defaultValue=""
                                                className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Selecciona si corresponde
                                                </option>
                                                {[
                                                    'Constancia firmada',
                                                    'Fotografía del documento',
                                                    'Archivo PDF',
                                                    'Imagen',
                                                    'Código de confirmación',
                                                    'Confirmación manual',
                                                ].map((tipo) => (
                                                    <option key={tipo}>
                                                        {tipo}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        <Field
                                            id="evidencia-entrega"
                                            label="Archivo de evidencia (opcional)"
                                            error={errors.evidencia}
                                        >
                                            <Input
                                                id="evidencia-entrega"
                                                name="evidencia"
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                PDF, JPG o PNG; máximo 10 MB.
                                            </p>
                                        </Field>
                                        <Field
                                            id="observacion-entrega"
                                            label="Observación"
                                            error={errors.observacion}
                                        >
                                            <Input
                                                id="observacion-entrega"
                                                name="observacion"
                                                maxLength={1000}
                                            />
                                        </Field>
                                        <FieldError id="entrega-error">
                                            {errors.entrega}
                                        </FieldError>
                                        <div className="sm:col-span-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : (
                                                    <PackageCheck data-icon="inline-start" />
                                                )}
                                                Registrar entrega
                                            </Button>
                                        </div>
                                    </FieldGroup>
                                )}
                            </Form>
                        )}

                        {puede_registrar_entrega && medios.length === 0 && (
                            <p className="text-sm text-destructive">
                                No hay medios de entrega activos. Ejecute el
                                sembrador de catálogos.
                            </p>
                        )}

                        {entrega && (
                            <div className="rounded-xl border bg-muted/20 p-4">
                                <div className="grid gap-3 text-sm sm:grid-cols-2">
                                    <Detail
                                        label="Medio"
                                        value={entrega.medio}
                                    />
                                    <Detail
                                        label="Fecha"
                                        value={
                                            entrega.fecha_entrega
                                                ? formatDateTime(
                                                      entrega.fecha_entrega,
                                                  )
                                                : 'Sin fecha'
                                        }
                                    />
                                    <Detail
                                        label="Receptor"
                                        value={`${entrega.receptor_nombre} · ${entrega.receptor_tipo}`}
                                    />
                                    <Detail
                                        label="Documento (enmascarado)"
                                        value={
                                            entrega.receptor_documento ??
                                            'No registrado'
                                        }
                                    />
                                    {entrega.correo_destino && (
                                        <Detail
                                            label="Correo de destino"
                                            value={entrega.correo_destino}
                                        />
                                    )}
                                    {entrega.medio_utilizado && (
                                        <Detail
                                            label="Medio utilizado"
                                            value={entrega.medio_utilizado}
                                        />
                                    )}
                                    <Detail
                                        label="Recepción"
                                        value={
                                            entrega.confirmado
                                                ? 'Confirmada'
                                                : 'Pendiente de confirmación'
                                        }
                                    />
                                </div>
                                {entrega.evidencias.length > 0 && (
                                    <ul className="mt-4 space-y-2 border-t pt-4 text-sm">
                                        {entrega.evidencias.map((evidencia) => (
                                            <li
                                                key={evidencia.id}
                                                className="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <span>
                                                    {evidencia.tipo}
                                                    {evidencia.nombre_original
                                                        ? ` · ${evidencia.nombre_original}`
                                                        : ''}
                                                </span>
                                                {evidencia.url_descarga && (
                                                    <a
                                                        href={
                                                            evidencia.url_descarga
                                                        }
                                                        className={buttonVariants(
                                                            {
                                                                variant:
                                                                    'outline',
                                                                size: 'sm',
                                                            },
                                                        )}
                                                    >
                                                        <Download data-icon="inline-start" />
                                                        Descargar
                                                    </a>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {!entrega.confirmado && (
                                    <p className="mt-4 border-t pt-4 text-sm text-muted-foreground">
                                        La recepción todavía no cuenta como
                                        entrega confirmada. La persona
                                        propietaria también puede confirmarla
                                        desde “Mis trámites”.
                                    </p>
                                )}
                            </div>
                        )}
                    </CardContent>
                    {entrega && !entrega.confirmado && (
                        <Form
                            {...TramiteEntregaController.confirm.form({
                                tramite: tramite.id,
                            })}
                        >
                            {({ errors, processing }) => (
                                <CardFooter className="flex flex-col items-start gap-3 border-t pt-4">
                                    <FieldGroup className="gap-3">
                                        <ShadcnField
                                            data-invalid={
                                                errors.confirmar
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <div className="flex items-start gap-3">
                                                <Checkbox
                                                    id="confirmar-recepcion-personal"
                                                    name="confirmar"
                                                    value="1"
                                                    required
                                                    aria-describedby={
                                                        errors.confirmar
                                                            ? 'confirmar-recepcion-personal-error'
                                                            : undefined
                                                    }
                                                    aria-invalid={
                                                        errors.confirmar
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                                <FieldLabel
                                                    htmlFor="confirmar-recepcion-personal"
                                                    className="items-start leading-5"
                                                >
                                                    Confirmo la recepción
                                                    registrada por el receptor.
                                                </FieldLabel>
                                            </div>
                                            <FieldError id="confirmar-recepcion-personal-error">
                                                {errors.confirmar}
                                            </FieldError>
                                        </ShadcnField>
                                        <FieldError id="confirmar-observacion-error">
                                            {errors.observacion}
                                        </FieldError>
                                    </FieldGroup>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <Check data-icon="inline-start" />
                                        )}
                                        Confirmar recepción
                                    </Button>
                                </CardFooter>
                            )}
                        </Form>
                    )}
                    {puede_anular && entrega && (
                        <Form
                            {...TramiteEntregaController.annul.form({
                                tramite: tramite.id,
                                entrega: entrega.id,
                            })}
                        >
                            {({ errors, processing }) => (
                                <CardFooter className="flex flex-col items-start gap-3 border-t pt-4">
                                    <FieldGroup className="w-full gap-3">
                                        <Field
                                            id="motivo-anulacion"
                                            label="Motivo de anulación"
                                            error={errors.motivo}
                                        >
                                            <Input
                                                id="motivo-anulacion"
                                                name="motivo"
                                                required
                                                minLength={10}
                                                maxLength={2000}
                                            />
                                        </Field>
                                    </FieldGroup>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <Ban data-icon="inline-start" />
                                        )}
                                        Anular entrega pendiente
                                    </Button>
                                </CardFooter>
                            )}
                        </Form>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Cierre e informe</CardTitle>
                        <CardDescription>
                            El expediente solo puede cerrarse después de que la
                            recepción haya sido confirmada.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {puede_cerrar && (
                            <Form
                                {...TramiteEntregaController.close.form({
                                    tramite: tramite.id,
                                })}
                            >
                                {({ errors, processing }) => (
                                    <FieldGroup className="gap-4">
                                        <Field
                                            id="resumen"
                                            label="Resumen de cierre"
                                            error={errors.resumen}
                                        >
                                            <Textarea
                                                id="resumen"
                                                name="resumen"
                                                required
                                                minLength={10}
                                                maxLength={2000}
                                                rows={4}
                                            />
                                        </Field>
                                        <Field
                                            id="observacion-cierre"
                                            label="Observación (opcional)"
                                            error={errors.observacion}
                                        >
                                            <Textarea
                                                id="observacion-cierre"
                                                name="observacion"
                                                maxLength={2000}
                                                rows={2}
                                            />
                                        </Field>
                                        <FieldError id="cierre-error">
                                            {errors.cierre}
                                        </FieldError>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <Check data-icon="inline-start" />
                                            )}
                                            Cerrar expediente y generar informe
                                        </Button>
                                    </FieldGroup>
                                )}
                            </Form>
                        )}

                        {cierre && (
                            <div className="rounded-xl border bg-muted/20 p-4 text-sm">
                                <p className="font-medium">
                                    Cierre registrado
                                    {cierre.fecha_cierre
                                        ? ` · ${formatDateTime(cierre.fecha_cierre)}`
                                        : ''}
                                </p>
                                <p className="mt-2 whitespace-pre-wrap">
                                    {cierre.resumen}
                                </p>
                                {cierre.observacion && (
                                    <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                                        {cierre.observacion}
                                    </p>
                                )}
                                {cierre.informe && (
                                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                                        <div>
                                            <p>
                                                {cierre.informe.numero_paginas}{' '}
                                                páginas · Código{' '}
                                                {
                                                    cierre.informe
                                                        .codigo_verificacion
                                                }
                                            </p>
                                            <p className="mt-1 text-xs break-all text-muted-foreground">
                                                SHA-256: {cierre.informe.sha256}
                                            </p>
                                        </div>
                                        <a
                                            href={cierre.informe.url_descarga}
                                            className={buttonVariants({
                                                variant: 'outline',
                                            })}
                                        >
                                            <Download data-icon="inline-start" />
                                            Descargar informe PDF
                                        </a>
                                    </div>
                                )}
                            </div>
                        )}
                        {puede_reabrir && (
                            <Form
                                {...TramiteEntregaController.reopen.form({
                                    tramite: tramite.id,
                                })}
                            >
                                {({ errors, processing }) => (
                                    <FieldGroup className="gap-3 border-t pt-4">
                                        <Field
                                            id="motivo-reapertura"
                                            label="Motivo de reapertura"
                                            error={errors.motivo}
                                        >
                                            <Input
                                                id="motivo-reapertura"
                                                name="motivo"
                                                required
                                                minLength={12}
                                                maxLength={2000}
                                            />
                                        </Field>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : (
                                                <RotateCcw data-icon="inline-start" />
                                            )}
                                            Reabrir expediente
                                        </Button>
                                    </FieldGroup>
                                )}
                            </Form>
                        )}
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
    children: ReactNode;
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

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p>{value}</p>
        </div>
    );
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

TramiteEntregaPage.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Entrega y cierre', href: '#' },
    ],
};
