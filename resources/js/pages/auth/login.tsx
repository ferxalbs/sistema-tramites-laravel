import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Iniciar Sesión" />

            <Card className="w-full shadow-sm border-border/80">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Iniciar Sesión
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Ingresa a tu cuenta para gestionar y consultar tus trámites
                    </CardDescription>
                    <CardAction>
                        <Button
                            variant="link"
                            className="text-xs font-medium text-primary hover:underline px-0"
                            render={<Link href={register()} />}
                        >
                            Crear cuenta
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent className="space-y-4">
                    <PasskeyVerify
                        label="Acceder con llave de paso (Passkey)"
                        loadingLabel="Autenticando..."
                        separator="O continúa con tus credenciales"
                    />

                    <Form
                        id="login-form"
                        {...store.form()}
                        resetOnSuccess={['password']}
                    >
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Correo electrónico</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        placeholder="usuario@correo.com"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="password">Contraseña</Label>
                                        {canResetPassword && (
                                            <Link
                                                href={request()}
                                                className="text-xs text-muted-foreground hover:text-foreground underline-offset-4 hover:underline transition-colors"
                                                tabIndex={5}
                                            >
                                                ¿Olvidaste tu contraseña?
                                            </Link>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="flex items-center gap-2 pt-1">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="text-xs font-normal text-muted-foreground cursor-pointer select-none"
                                    >
                                        Mantener sesión iniciada
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full mt-2"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Ingresando...
                                        </>
                                    ) : (
                                        'Ingresar al Sistema'
                                    )}
                                </Button>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex flex-col gap-2 pt-0 pb-6 text-center text-xs text-muted-foreground">
                    <p>
                        ¿Tienes dudas sobre tu acceso?{' '}
                        <Link href="/ayuda" className="text-primary underline underline-offset-4">
                            Visita el Centro de Ayuda
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
