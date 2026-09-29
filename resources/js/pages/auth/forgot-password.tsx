import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar Contraseña" />

            <Card className="w-full shadow-sm border-border/80">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Recuperar Contraseña
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Ingresa tu correo electrónico para recibir un enlace de restablecimiento
                    </CardDescription>
                    <CardAction>
                        <Button
                            variant="link"
                            className="text-xs font-medium text-primary hover:underline px-0"
                            render={<Link href={login()} />}
                        >
                            Iniciar sesión
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <Form id="forgot-password-form" {...email.form()}>
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Correo electrónico</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        autoFocus
                                        placeholder="usuario@correo.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full mt-2"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Enviando enlace...
                                        </>
                                    ) : (
                                        'Enviar enlace de recuperación'
                                    )}
                                </Button>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex flex-col gap-2 pt-0 pb-6 text-center text-xs text-muted-foreground">
                    <p>
                        ¿Recordaste tu contraseña?{' '}
                        <Link href={login()} className="text-primary underline underline-offset-4">
                            Volver al inicio de sesión
                        </Link>
                    </p>
                </CardFooter>
            </Card>

            {status && (
                <div className="mt-4 rounded-lg bg-green-500/10 border border-green-500/20 p-3 text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {status}
                </div>
            )}
        </>
    );
}
