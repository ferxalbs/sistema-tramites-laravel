import { Form, Head } from '@inertiajs/react';
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

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificación de Correo" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-lg border border-green-500/20 bg-green-500/10 p-3 text-center text-sm font-medium text-green-600 dark:text-green-400">
                    Se ha enviado un nuevo enlace de verificación al correo
                    electrónico que proporcionaste durante el registro.
                </div>
            )}

            <Card className="w-full border-border/80 shadow-sm">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Verificación de Correo
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Por favor verifica tu correo electrónico haciendo clic
                        en el enlace que te acabamos de enviar.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <Form
                        id="verify-email-form"
                        {...send.form()}
                        className="text-center"
                    >
                        {({ processing }) => (
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
