import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleHelp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

type SupportPageProps = {
    faq: Record<string, { question: string; answer: string }>;
    tutorials: Array<{ title: string; steps: string[] }>;
    role_guide: Array<{ title: string; description: string }>;
    contact: Record<string, string>;
    whatsapp_url: string | null;
};

const contactLabels: Record<string, string> = {
    email: 'Correo',
    phone: 'Teléfono',
    whatsapp: 'WhatsApp',
    hours: 'Horario',
    address: 'Dirección',
};

export default function Ayuda({
    faq,
    tutorials,
    role_guide,
    contact,
    whatsapp_url,
}: SupportPageProps) {
    return (
        <>
            <Head title="Ayuda y soporte" />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8 md:py-12">
                <header className="flex items-start gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        render={<Link href={home()} />}
                        aria-label="Volver al inicio"
                    >
                        <ArrowLeft />
                    </Button>
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <CircleHelp className="size-6" /> Ayuda y soporte
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Orientación para consultar y seguir un trámite
                            registrado en Mesa de Partes.
                        </p>
                    </div>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Preguntas frecuentes</CardTitle>
                        <CardDescription>
                            Respuestas generales sin información de expedientes
                            personales.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {Object.entries(faq).map(([key, item]) => (
                            <details
                                key={key}
                                className="rounded-xl border p-4"
                            >
                                <summary className="cursor-pointer font-medium">
                                    {item.question}
                                </summary>
                                <p className="mt-3 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                    {item.answer}
                                </p>
                            </details>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tutoriales breves</CardTitle>
                        <CardDescription>
                            Pasos generales para usar el sistema.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        {tutorials.map((tutorial) => (
                            <section
                                key={tutorial.title}
                                className="rounded-xl border p-4"
                            >
                                <h2 className="font-medium">
                                    {tutorial.title}
                                </h2>
                                <ol className="mt-2 list-inside list-decimal text-sm leading-6 text-muted-foreground">
                                    {tutorial.steps.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ol>
                            </section>
                        ))}
                    </CardContent>
                </Card>

                {role_guide.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Guía de mi rol</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {role_guide.map((item) => (
                                <div
                                    key={item.title}
                                    className="rounded-xl border p-4"
                                >
                                    <h2 className="font-medium">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Contacto</CardTitle>
                        <CardDescription>
                            Los canales oficiales dependen de la configuración
                            institucional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {Object.keys(contact).length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aún no se ha configurado un canal de contacto.
                            </p>
                        ) : (
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                {Object.entries(contact).map(([key, value]) => (
                                    <div key={key}>
                                        <dt className="font-medium">
                                            {contactLabels[key] ?? key}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        {whatsapp_url && (
                            <Button
                                className="self-start"
                                render={
                                    <a
                                        href={whatsapp_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    />
                                }
                            >
                                Abrir WhatsApp
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
