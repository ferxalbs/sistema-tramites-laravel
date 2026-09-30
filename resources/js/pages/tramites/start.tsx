import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    tipos: Array<{ codigo: string; nombre: string }>;
};

export default function TramiteStart({ tipos }: Props) {
    return (
        <>
            <Head title="Iniciar trámite" />
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex items-start gap-3">
                    <Button render={<Link href={TramiteController.index()} />} variant="outline" size="icon" aria-label="Volver a la bandeja">
                        <ArrowLeft />
                    </Button>
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">Mesa de Partes</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Iniciar registro</h1>
                        <p className="text-sm text-muted-foreground">Identifica al solicitante y selecciona el trámite para abrir su formulario.</p>
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
                                <CardTitle>Datos para iniciar</CardTitle>
                                <CardDescription>El DNI permite vincular los datos del perfil estudiantil. El código interno se asigna al guardar el expediente.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
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
                                    <p className="text-xs text-muted-foreground">Si pertenece a un estudiante registrado, el formulario siguiente mostrará sus datos de perfil.</p>
                                    <InputError message={errors.dni} />
                                </div>

                                <div className="grid content-start gap-2">
                                    <Label htmlFor="tipo_documento">Tipo de trámite</Label>
                                    <select
                                        id="tipo_documento"
                                        name="tipo_documento"
                                        required
                                        defaultValue=""
                                        className="h-9 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="" disabled>Selecciona el tipo de trámite</option>
                                        {tipos.map((tipo) => <option key={tipo.codigo} value={tipo.codigo}>{tipo.nombre}</option>)}
                                    </select>
                                    <InputError message={errors.tipo_documento} />
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing || tipos.length === 0}>
                                        {processing ? <Spinner /> : <ArrowRight data-icon="inline-start" />}
                                        Digitalizar solicitud
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
