import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar Contraseña" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirmar con llave de paso (Passkey)"
                loadingLabel="Confirmando..."
                separator="O confirma con tu contraseña"
            />

            <Card className="w-full shadow-sm border-border/80">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        Confirmar Contraseña
                    </CardTitle>
                    <CardDescription className="text-sm">
                        Esta es un área protegida del sistema. Por favor confirma tu contraseña antes de continuar.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form id="confirm-password-form" {...store.form()} resetOnSuccess={['password']}>
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Contraseña</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="••••••••"
                                        autoComplete="current-password"
                                        autoFocus
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full mt-2"
                                    disabled={processing}
                                    data-test="confirm-password-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Confirmando...
                                        </>
                                    ) : (
                                        'Confirmar contraseña'
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
