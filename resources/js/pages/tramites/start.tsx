import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useState } from 'react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type TipoTramite = { codigo: string; nombre: string; requiere_solicitante: boolean };

type Props = {
    tipos: TipoTramite[];
};

export default function TramiteStart({ tipos }: Props) {
    const [tipoDocumento, setTipoDocumento] = useState('');
    const tipoSeleccionado = tipos.find((tipo) => tipo.codigo === tipoDocumento);
    const solicitudes = tipos.filter((tipo) => tipo.requiere_solicitante);
    const documentosInstitucionales = tipos.filter((tipo) => !tipo.requiere_solicitante);

    return (
        <>
            <Head title="Iniciar registro" />
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button render={<Link href={TramiteController.index()} />} variant="outline" size="icon" aria-label="Volver a la bandeja">
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">Registro documental</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Iniciar registro</h1>
                        <p className="text-sm text-muted-foreground">Selecciona una solicitud o un documento institucional para abrir su formulario.</p>
                    </div>
                </header>

                <Form action={TramiteController.start.url()} method="post" options={{ preserveScroll: true }}>
                    {({ errors, processing }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>{tipoSeleccionado?.requiere_solicitante === false ? 'Documento institucional' : 'Solicitud'}</CardTitle>
                                <CardDescription>
                                    {tipoSeleccionado?.requiere_solicitante === false
                                        ? 'Los informes y memorandos no requieren datos ni DNI de una persona solicitante.'
                                        : 'Para registrar una solicitud, ingresa el DNI del solicitante. Los informes y memorandos se registran sin DNI.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="tipo_documento">Tipo de registro</Label>
                                    <select
                                        id="tipo_documento"
                                        name="tipo_documento"
                                        required
                                        value={tipoDocumento}
                                        onChange={(event) => setTipoDocumento(event.target.value)}
                                        className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="" disabled>Selecciona el tipo de registro</option>
                                        {solicitudes.length > 0 && (
                                            <optgroup label="Solicitud">
                                                {solicitudes.map((tipo) => <option key={tipo.codigo} value={tipo.codigo}>{tipo.nombre}</option>)}
                                            </optgroup>
                                        )}
                                        {documentosInstitucionales.length > 0 && (
                                            <optgroup label="Documento institucional">
                                                {documentosInstitucionales.map((tipo) => <option key={tipo.codigo} value={tipo.codigo}>{tipo.nombre}</option>)}
                                            </optgroup>
                                        )}
                                    </select>
                                    <InputError message={errors.tipo_documento} />
                                </div>

                                {tipoSeleccionado?.requiere_solicitante && (
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="dni">DNI del solicitante</Label>
                                        <Input
                                            id="dni"
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
                                        <p className="text-xs text-muted-foreground">Si pertenece a un estudiante registrado, el formulario mostrará los datos de su perfil.</p>
                                        <InputError message={errors.dni} />
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing || tipoSeleccionado === undefined}>
                                        {processing ? <Spinner /> : <ArrowRight data-icon="inline-start" />}
                                        {tipoSeleccionado?.requiere_solicitante === false ? 'Registrar documento' : 'Digitalizar solicitud'}
                                    </Button>
                                </div>
                            </CardContent>
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
