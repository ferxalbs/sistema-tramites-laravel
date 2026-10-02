import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Código de recuperación',
                description:
                    'Por favor confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia.',
                toggleText: 'iniciar sesión con código de autenticación',
            };
        }

        return {
            title: 'Autenticación en dos pasos',
            description:
                'Ingresa el código de 6 dígitos generado por tu aplicación autenticadora.',
            toggleText: 'usar código de recuperación de emergencia',
        };
    }, [showRecoveryInput]);

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Autenticación de dos factores" />

            <Card className="w-full border-border/80 shadow-sm">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-xl font-bold tracking-tight">
                        {authConfigContent.title}
                    </CardTitle>
                    <CardDescription className="text-sm">
                        {authConfigContent.description}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        id="two-factor-form"
                        {...store.form()}
                        className="flex flex-col gap-4"
                        resetOnError
                        resetOnSuccess={!showRecoveryInput}
                    >
                        {({ errors, processing, clearErrors }) => (
                            <>
                                {showRecoveryInput ? (
                                    <div className="grid gap-2">
                                        <Input
                                            name="recovery_code"
                                            type="text"
                                            placeholder="Ingresa código de recuperación"
                                            autoFocus={showRecoveryInput}
                                            required
                                        />
                                        <InputError
                                            message={errors.recovery_code}
                                        />
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center gap-3 text-center">
                                        <div className="flex w-full items-center justify-center">
                                            <InputOTP
                                                name="code"
                                                maxLength={OTP_MAX_LENGTH}
                                                value={code}
                                                onChange={(value) =>
                                                    setCode(value)
                                                }
                                                disabled={processing}
                                                pattern={REGEXP_ONLY_DIGITS}
                                                autoFocus
                                            >
                                                <InputOTPGroup>
                                                    {Array.from(
                                                        {
                                                            length: OTP_MAX_LENGTH,
                                                        },
                                                        (_, index) => (
                                                            <InputOTPSlot
                                                                key={index}
                                                                index={index}
                                                            />
                                                        ),
                                                    )}
                                                </InputOTPGroup>
                                            </InputOTP>
                                        </div>
                                        <InputError message={errors.code} />
                                    </div>
                                )}

                                <div className="text-center text-xs text-muted-foreground">
                                    <span>
                                        ¿Problemas con el código? También
                                        puedes{' '}
                                    </span>
                                    <button
                                        type="button"
                                        className="cursor-pointer text-primary underline underline-offset-4 hover:opacity-80"
                                        onClick={() =>
                                            toggleRecoveryMode(clearErrors)
                                        }
                                    >
                                        {authConfigContent.toggleText}
                                    </button>
                                </div>

                                <Button
                                    type="submit"
                                    form="two-factor-form"
                                    className="mt-2 w-full"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2 h-4 w-4" />
                                            Verificando...
                                        </>
                                    ) : (
                                        'Continuar'
                                    )}
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </>
    );
}
