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
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div className="text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Forgot password</CardTitle>
                    <CardDescription>
                        Enter your email to receive a password reset link
                    </CardDescription>
                    <CardAction>
                        <Button
                            variant="link"
                            render={<Link href={login()} />}
                        >
                            Log In
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <Form id="forgot-password-form" {...email.form()}>
                        {({ errors }) => (
                            <div className="flex flex-col gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        autoFocus
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <Button
                        type="submit"
                        form="forgot-password-form"
                        className="w-full"
                        data-test="email-password-reset-link-button"
                    >
                        Email password reset link
                    </Button>
                </CardFooter>
            </Card>
        </>
    );
}
