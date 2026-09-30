import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/admin/output-formats';
import { index as templatesIndex } from '@/routes/admin/templates';

type OutputFormat = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    permite_modalidad_multiple: boolean | number;
};

type PublishedTemplate = {
    id: number;
    codigo: string;
    nombre: string;
    version: number;
    tipo_documento_salida: string;
    modalidad: string | null;
};

export default function FormatosSalida({
    formatos,
    plantillasFinales,
}: {
    formatos: OutputFormat[];
    plantillasFinales: PublishedTemplate[];
}) {
    return (
        <>
            <Head title="Formatos de salida" />
            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Formatos de salida
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Los formatos se mantienen disponibles. Aquí puedes revisar sus plantillas finales publicadas.
                    </p>
                </header>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {formatos.map((formato) => {
                        const versiones = plantillasFinales.filter(
                            (plantilla) => plantilla.tipo_documento_salida === formato.codigo,
                        );

                        return (
                            <Card key={formato.id} className="transition-all hover:border-foreground/20">
                                <CardHeader>
                                    <CardTitle>{formato.codigo}</CardTitle>
                                    <CardDescription>
                                        {formato.permite_modalidad_multiple
                                            ? 'Admite memorando simple o múltiple.'
                                            : 'Sin modalidad múltiple.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5">
                                    <section aria-label={`Plantillas publicadas para ${formato.nombre}`}>
                                        <h2 className="mb-2 text-sm font-medium">
                                            Versiones finales publicadas
                                        </h2>
                                        {versiones.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                Todavía no hay una plantilla final publicada para este formato.
                                            </p>
                                        ) : (
                                            <ul className="space-y-1 text-sm">
                                                {versiones.map((plantilla) => (
                                                    <li key={plantilla.id}>
                                                        {plantilla.nombre} · v{plantilla.version}
                                                        {plantilla.modalidad ? ` · ${plantilla.modalidad}` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        <Button
                                            className="mt-3"
                                            variant="outline"
                                            render={<Link href={templatesIndex()} />}
                                        >
                                            Administrar plantillas
                                        </Button>
                                    </section>

                                    <Form {...update.form({ format: formato.id })} disableWhileProcessing>
                                        {({ errors, processing }) => (
                                            <>
                                                <div className="grid gap-3">
                                                    <Label htmlFor={`nombre-${formato.id}`}>
                                                        Nombre
                                                    </Label>
                                                    <Input
                                                        id={`nombre-${formato.id}`}
                                                        name="nombre"
                                                        required
                                                        minLength={2}
                                                        maxLength={120}
                                                        defaultValue={formato.nombre}
                                                    />
                                                    <InputError message={errors.nombre} />
                                                    <Label htmlFor={`descripcion-${formato.id}`}>
                                                        Descripción
                                                    </Label>
                                                    <Input
                                                        id={`descripcion-${formato.id}`}
                                                        name="descripcion"
                                                        maxLength={255}
                                                        defaultValue={formato.descripcion ?? ''}
                                                    />
                                                    <InputError message={errors.descripcion} />
                                                </div>
                                                <CardFooter className="px-0 pb-0">
                                                    <Button type="submit" disabled={processing}>
                                                        Guardar cambios
                                                    </Button>
                                                </CardFooter>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
