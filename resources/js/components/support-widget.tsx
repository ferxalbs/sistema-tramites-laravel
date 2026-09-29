import { Link, usePage } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as supportIndex } from '@/routes/support';

type SupportProps = {
    assistant_topics: Record<string, { question: string; answer: string }>;
    whatsapp_url: string | null;
};

export default function SupportWidget() {
    const { support } = usePage<{ support: SupportProps }>().props;
    const [selectedTopic, setSelectedTopic] = useState<string | null>(null);
    const topic = selectedTopic
        ? support.assistant_topics[selectedTopic]
        : null;

    return (
        <Dialog
            onOpenChange={(open) => {
                if (!open) setSelectedTopic(null);
            }}
        >
            <DialogTrigger
                render={<Button className="fixed right-4 bottom-4 shadow-lg" />}
            >
                <CircleHelp data-icon="inline-start" />
                ¿Necesitas ayuda?
            </DialogTrigger>
            <DialogContent className="max-h-[min(90vh,42rem)] overflow-y-auto">
                <DialogTitle>Asistente de ayuda</DialogTitle>
                <DialogDescription>
                    Elija una pregunta para recibir orientación.
                </DialogDescription>
                {topic ? (
                    <section className="flex flex-col gap-4">
                        <h3 className="font-medium">{topic.question}</h3>
                        <p className="whitespace-pre-wrap text-muted-foreground">
                            {topic.answer}
                        </p>
                        {selectedTopic === 'whatsapp' &&
                            support.whatsapp_url && (
                                <Button
                                    render={
                                        <a
                                            href={support.whatsapp_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        />
                                    }
                                >
                                    Abrir WhatsApp
                                </Button>
                            )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedTopic(null)}
                            >
                                Volver
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setSelectedTopic(null)}
                            >
                                Reiniciar
                            </Button>
                        </div>
                    </section>
                ) : (
                    <div
                        className="flex flex-col gap-2"
                        role="list"
                        aria-label="Preguntas de ayuda"
                    >
                        {Object.entries(support.assistant_topics).map(
                            ([key, item]) => (
                                <Button
                                    key={key}
                                    type="button"
                                    variant="outline"
                                    className="h-auto justify-start text-left whitespace-normal"
                                    onClick={() => setSelectedTopic(key)}
                                >
                                    {item.question}
                                </Button>
                            ),
                        )}
                    </div>
                )}
                <DialogFooter className="items-center justify-between">
                    <Link
                        href={supportIndex()}
                        className="text-sm text-primary underline-offset-4 hover:underline"
                    >
                        Preguntas frecuentes
                    </Link>
                    <DialogClose render={<Button variant="secondary" />}>
                        Cerrar
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
