import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Restablecer Contraseña" />

            <Card className="w-full border-border/80 shadow-sm">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Restablecer Contraseña
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Ingresa tu nueva contraseña para actualizar el acceso
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        id="reset-password-form"
                        {...update.form()}
                        transform={(data) => ({ ...data, token, email })}
                        resetOnSuccess={['password', 'password_confirmation']}
                    >
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        Correo electrónico
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        value={email}
                                        readOnly
                                        className="bg-muted text-muted-foreground"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Nueva contraseña
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        autoComplete="new-password"
                                        autoFocus
                                        placeholder="••••••••"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirmar nueva contraseña
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        placeholder="••••••••"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-2 w-full"
                                    disabled={processing}
                                    data-test="reset-password-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Actualizando contraseña...
                                        </>
                                    ) : (
                                        'Restablecer contraseña'
                                    )}
                                </Button>
                            </div>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </>
    );
}
