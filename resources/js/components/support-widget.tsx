import { Link, usePage } from '@inertiajs/react';
import { CircleHelp, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
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
    const topic = selectedTopic ? support.assistant_topics[selectedTopic] : null;

    return (
        <Dialog
            onOpenChange={(open) => {
                if (!open) setSelectedTopic(null);
            }}
        >
            <DialogTrigger render={<Button className="fixed right-4 bottom-4 shadow-lg" />}>
                <CircleHelp data-icon="inline-start" />
                ¿Necesitas ayuda?
            </DialogTrigger>
            <DialogContent className="max-h-[min(90vh,42rem)] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Asistente de ayuda</DialogTitle>
                    <DialogDescription>
                        Elige una pregunta para recibir orientación.
                    </DialogDescription>
                </DialogHeader>

                {topic ? (
                    <div className="flex flex-col gap-4 py-2">
                        <h3 className="text-base font-semibold">{topic.question}</h3>
                        <p className="whitespace-pre-wrap text-sm text-muted-foreground leading-relaxed">
                            {topic.answer}
                        </p>
                        {selectedTopic === 'whatsapp' && support.whatsapp_url && (
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
                        <div className="flex gap-2 pt-2">
                            <Button type="button" variant="outline" onClick={() => setSelectedTopic(null)}>
                                Volver
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setSelectedTopic(null)}>
                                Reiniciar
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2 py-2" role="list" aria-label="Preguntas de ayuda">
                        {Object.entries(support.assistant_topics).map(([key, item]) => (
                            <button
                                key={key}
                                type="button"
                                className="flex w-full items-center justify-between rounded-xl border border-border/80 bg-card p-3 text-left text-sm text-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                onClick={() => setSelectedTopic(key)}
                            >
                                <span>{item.question}</span>
                                <ChevronRight className="ml-2 size-4 shrink-0 text-muted-foreground" />
                            </button>
                        ))}
                    </div>
                )}

                <DialogFooter className="flex-row items-center justify-between sm:justify-between">
                    <Link
                        href={supportIndex()}
                        className="text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        Preguntas frecuentes
                    </Link>
                    <DialogClose render={<Button variant="secondary" size="sm" />}>
                        Cerrar
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
