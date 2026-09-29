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
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
            />

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Confirm password</CardTitle>
                    <CardDescription>
                        This is a secure area of the application. Please confirm your password before continuing.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form id="confirm-password-form" {...store.form()} resetOnSuccess={['password']}>
                        {({ errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        autoFocus
                                    />
                                    <InputError message={errors.password} />
                                </div>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <Button
                        type="submit"
                        form="confirm-password-form"
                        className="w-full"
                        data-test="confirm-password-button"
                    >
                        Confirm password
                    </Button>
                </CardFooter>
            </Card>
        </>
    );
}
