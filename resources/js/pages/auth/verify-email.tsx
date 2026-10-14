import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { markLoggedOut } from '@/lib/auth-history';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({
    status,
    bandeja_pruebas,
}: {
    status?: string;
    bandeja_pruebas: boolean;
}) {
    return (
        <>
            <Head title="Verificación de Correo" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-lg border border-primary/20 bg-primary/5 p-3 text-center text-sm font-medium text-foreground">
                    {bandeja_pruebas
                        ? 'El nuevo enlace está en la bandeja local de pruebas; no se envió a un correo externo.'
                        : 'El servidor de correo aceptó el nuevo enlace de verificación.'}
                </div>
            )}

            <Card className="w-full border-border/80 shadow-sm">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Verificación de Correo
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Verifica tu dirección usando el enlace recibido. Puedes
                        solicitar un nuevo enlace si lo necesitas.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <Form
                        id="verify-email-form"
                        {...send.form()}
                        className="text-center"
                    >
                        {({ processing, errors }) => (
                            <>
                                <InputError message={errors.email} />
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    variant="secondary"
                                    className="w-full"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Reenviando correo...
                                        </>
                                    ) : (
                                        'Reenviar correo de verificación'
                                    )}
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <TextLink
                        href={logout()}
                        className="mx-auto block text-sm"
                        onClick={markLoggedOut}
                    >
                        Cerrar sesión
                    </TextLink>
                </CardFooter>
            </Card>
        </>
    );
}
