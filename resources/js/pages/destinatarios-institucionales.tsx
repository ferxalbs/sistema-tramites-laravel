import { Form, Head } from '@inertiajs/react';
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
import { store, update } from '@/routes/admin/recipients';

type Recipient = {
    id: number;
    codigo: string;
    nombres: string;
    apellidos: string;
    cargo: string;
    correo: string | null;
    activo: boolean | number;
};

function RecipientFields({
    recipient,
    errors,
}: {
    recipient?: Recipient;
    errors: Record<string, string>;
}) {
    const prefix = recipient?.id ?? 'nuevo';

    return (
        <CardContent className="grid gap-4 sm:grid-cols-2">
            {(
                [
                    ['nombres', 'Nombres', recipient?.nombres ?? '', 120],
                    ['apellidos', 'Apellidos', recipient?.apellidos ?? '', 120],
                    ['cargo', 'Cargo o función', recipient?.cargo ?? '', 180],
                    [
                        'correo',
                        'Correo (opcional)',
                        recipient?.correo ?? '',
                        255,
                    ],
                ] as const
            ).map(([name, label, value, maxLength]) => (
                <div key={name} className="grid gap-2">
                    <Label htmlFor={`${name}-${prefix}`}>{label}</Label>
                    <Input
                        id={`${name}-${prefix}`}
                        name={name}
                        type={name === 'correo' ? 'email' : 'text'}
                        defaultValue={value}
                        required={name !== 'correo'}
                        maxLength={maxLength}
                    />
                    <InputError message={errors[name]} />
                </div>
            ))}
            <div className="grid gap-2">
                <Label htmlFor={`activo-${prefix}`}>Estado</Label>
                <select
                    id={`activo-${prefix}`}
                    name="activo"
                    defaultValue={
                        recipient?.activo === false || recipient?.activo === 0
                            ? '0'
                            : '1'
                    }
                    className="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
                <InputError message={errors.activo} />
            </div>
        </CardContent>
    );
}

export default function DestinatariosInstitucionales({
    destinatarios,
}: {
    destinatarios: Recipient[];
}) {
    return (
        <>
            <Head title="Destinatarios institucionales" />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Destinatarios institucionales</CardTitle>
                        <CardDescription>
                            Estas personas aparecen como sugerencias al preparar
                            un documento. Cambiar una ficha no altera los
                            documentos ya guardados.
                        </CardDescription>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Agregar destinatario</CardTitle>
                    </CardHeader>
                    <Form
                        {...store.form()}
                        onBefore={() =>
                            window.confirm('¿Deseas agregar este destinatario?')
                        }
                        disableWhileProcessing
                    >
                        {({ errors, processing }) => (
                            <>
                                <RecipientFields errors={errors} />
                                <CardFooter>
                                    <Button type="submit" disabled={processing}>
                                        Agregar
                                    </Button>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
                {destinatarios.map((recipient) => (
                    <Card key={recipient.id}>
                        <CardHeader>
                            <CardTitle>
                                {recipient.nombres} {recipient.apellidos}
                            </CardTitle>
                            <CardDescription>{recipient.cargo}</CardDescription>
                        </CardHeader>
                        <Form
                            {...update.form({ recipient: recipient.id })}
                            onBefore={() =>
                                window.confirm('¿Deseas guardar estos cambios?')
                            }
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <RecipientFields
                                        recipient={recipient}
                                        errors={errors}
                                    />
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar cambios
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    </Card>
                ))}
            </main>
        </>
    );
}
