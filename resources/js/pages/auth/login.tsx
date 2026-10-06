import { Form, Head, Link } from '@inertiajs/react';
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
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { create as requestTeacherAccess } from '@/routes/teacher-access';

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
                        Ingresa a tu cuenta para gestionar y consultar tus
                        trámites
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
                        {({ errors }) => (
                            <FieldGroup>
                                <Field data-invalid={Boolean(errors.email)}>
                                    <FieldLabel htmlFor="email">
                                        Correo electrónico
                                    </FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        placeholder="usuario@correo.com"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        aria-invalid={Boolean(errors.email)}
                                        aria-describedby={
                                            errors.email
                                                ? 'email-error'
                                                : undefined
                                        }
                                    />
                                    <FieldError id="email-error">
                                        {errors.email}
                                    </FieldError>
                                </Field>
                                <Field data-invalid={Boolean(errors.password)}>
                                    <div className="flex items-center">
                                        <FieldLabel htmlFor="password">
                                            Contraseña
                                        </FieldLabel>
                                        {canResetPassword && (
                                            <Link
                                                href={request()}
                                                className="ml-auto inline-block text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
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
                                        aria-invalid={Boolean(errors.password)}
                                        aria-describedby={
                                            errors.password
                                                ? 'password-error'
                                                : undefined
                                        }
                                    />
                                    <FieldError id="password-error">
                                        {errors.password}
                                    </FieldError>
                                </Field>
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">
                                        Recordar sesión
                                    </Label>
                                </Field>
                            </FieldGroup>
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
                    <Button
                        variant="link"
                        render={<Link href={requestTeacherAccess()} />}
                    >
                        Solicitar acceso docente
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
