import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
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

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Iniciar Sesión</CardTitle>
                    <CardDescription>
                        Ingresa a tu cuenta para gestionar y consultar tus trámites
                    </CardDescription>
                    <CardAction>
                        <Button
                            variant="link"
                            render={<Link href={register()} />}
                        >
                            Crear cuenta
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <Form
                        id="login-form"
                        {...store.form()}
                        resetOnSuccess={['password']}
                    >
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-6">
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
                                    <div className="flex items-center">
                                        <Label htmlFor="password">Contraseña</Label>
                                        {canResetPassword && (
                                            <Link
                                                href={request()}
                                                className="ml-auto inline-block text-sm text-muted-foreground underline-offset-4 hover:underline hover:text-foreground"
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
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">
                                        Recordar sesión
                                    </Label>
                                </div>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <Button
                        type="submit"
                        form="login-form"
                        className="w-full"
                        tabIndex={4}
                        data-test="login-button"
                    >
                        Iniciar Sesión
                    </Button>
                </CardFooter>
            </Card>

            {status && (
                <div className="text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}
