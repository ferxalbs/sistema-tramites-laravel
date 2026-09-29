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
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Email verification</CardTitle>
                    <CardDescription>
                        Please verify your email address by clicking on the link we just emailed to you.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <Form id="verify-email-form" {...send.form()} className="text-center">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                variant="secondary"
                                className="w-full"
                            >
                                {processing && <Spinner />}
                                Resend verification email
                            </Button>
                        )}
                    </Form>
                </CardContent>
                <CardFooter className="flex-col gap-2">
                    <TextLink
                        href={logout()}
                        className="mx-auto block text-sm"
                    >
                        Log out
                    </TextLink>
                </CardFooter>
            </Card>
        </>
    );
}
