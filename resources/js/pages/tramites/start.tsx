import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useState } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardFooter,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type TipoTramite = {
    codigo: string;
    nombre: string;
    requiere_solicitante: boolean;
};

type Props = {
    tipos: TipoTramite[];
};

export default function TramiteStart({ tipos }: Props) {
    const [tipoDocumento, setTipoDocumento] = useState('');
    const tipoSeleccionado = tipos.find(
        (tipo) => tipo.codigo === tipoDocumento,
    );
    const solicitudes = tipos.filter((tipo) => tipo.requiere_solicitante);
    const documentosInstitucionales = tipos.filter(
        (tipo) => !tipo.requiere_solicitante,
    );

    return (
        <>
            <Head title="Iniciar registro" />
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button
                        render={<Link href={TramiteController.index()} />}
                        variant="outline"
                        size="icon"
                        aria-label="Volver a la bandeja"
                    >
                        <ArrowLeft />
                    </Button>
                    <div className="flex flex-col gap-1">
                        <p className="text-sm text-muted-foreground">
                            Registro documental
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Iniciar registro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Selecciona una solicitud o un documento
                            institucional para abrir su formulario.
                        </p>
                    </div>
                </header>

                <Form
                    action={TramiteController.start.url()}
                    method="post"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {tipoSeleccionado?.requiere_solicitante ===
                                    false
                                        ? 'Documento institucional'
                                        : 'Solicitud'}
                                </CardTitle>
                                <CardDescription>
                                    {tipoSeleccionado?.requiere_solicitante ===
                                    false
                                        ? 'Los informes y memorandos no requieren datos ni DNI de una persona solicitante.'
                                        : 'Para registrar una solicitud, ingresa el DNI del solicitante. Los informes y memorandos se registran sin DNI.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <Field
                                        data-invalid={Boolean(
                                            errors.tipo_documento,
                                        )}
                                    >
                                        <FieldLabel htmlFor="tipo_documento">
                                            Tipo de registro
                                        </FieldLabel>
                                        <Select
                                            name="tipo_documento"
                                            required
                                            items={tipos.map((tipo) => ({
                                                value: tipo.codigo,
                                                label: tipo.nombre,
                                            }))}
                                            value={tipoDocumento || null}
                                            onValueChange={(value) =>
                                                setTipoDocumento(value ?? '')
                                            }
                                        >
                                            <SelectTrigger
                                                id="tipo_documento"
                                                className="w-full"
                                                aria-invalid={Boolean(
                                                    errors.tipo_documento,
                                                )}
                                                aria-describedby={
                                                    errors.tipo_documento
                                                        ? 'tipo_documento-error'
                                                        : undefined
                                                }
                                            >
                                                <SelectValue placeholder="Selecciona el tipo de registro" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {solicitudes.length > 0 && (
                                                    <SelectGroup>
                                                        <SelectLabel>
                                                            Solicitud
                                                        </SelectLabel>
                                                        {solicitudes.map(
                                                            (tipo) => (
                                                                <SelectItem
                                                                    key={
                                                                        tipo.codigo
                                                                    }
                                                                    value={
                                                                        tipo.codigo
                                                                    }
                                                                >
                                                                    {
                                                                        tipo.nombre
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                )}
                                                {documentosInstitucionales.length >
                                                    0 && (
                                                    <SelectGroup>
                                                        <SelectLabel>
                                                            Documento
                                                            institucional
                                                        </SelectLabel>
                                                        {documentosInstitucionales.map(
                                                            (tipo) => (
                                                                <SelectItem
                                                                    key={
                                                                        tipo.codigo
                                                                    }
                                                                    value={
                                                                        tipo.codigo
                                                                    }
                                                                >
                                                                    {
                                                                        tipo.nombre
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <FieldError id="tipo_documento-error">
                                            {errors.tipo_documento}
                                        </FieldError>
                                    </Field>

                                    {tipoSeleccionado?.requiere_solicitante && (
                                        <Field
                                            data-invalid={Boolean(errors.dni)}
                                        >
                                            <FieldLabel htmlFor="dni">
                                                DNI del solicitante
                                            </FieldLabel>
                                            <Input
                                                id="dni"
                                                aria-invalid={Boolean(
                                                    errors.dni,
                                                )}
                                                aria-describedby={
                                                    errors.dni
                                                        ? 'dni-description dni-error'
                                                        : 'dni-description'
                                                }
                                                name="dni"
                                                type="text"
                                                inputMode="numeric"
                                                autoComplete="off"
                                                pattern="[0-9]{8}"
                                                minLength={8}
                                                maxLength={8}
                                                required
                                                placeholder="Ingresa los 8 dígitos"
                                            />
                                            <FieldDescription id="dni-description">
                                                Si pertenece a un estudiante
                                                registrado, el formulario
                                                mostrará los datos de su perfil.
                                            </FieldDescription>
                                            <FieldError id="dni-error">
                                                {errors.dni}
                                            </FieldError>
                                        </Field>
                                    )}
                                </FieldGroup>
                            </CardContent>
                            <CardFooter className="justify-end">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        tipoSeleccionado === undefined
                                    }
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <ArrowRight data-icon="inline-start" />
                                    )}
                                    {tipoSeleccionado?.requiere_solicitante ===
                                    false
                                        ? 'Registrar documento'
                                        : 'Digitalizar solicitud'}
                                </Button>
                            </CardFooter>
                        </Card>
                    )}
                </Form>
            </main>
        </>
    );
}

TramiteStart.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
        { title: 'Iniciar registro', href: TramiteController.create() },
    ],
};
