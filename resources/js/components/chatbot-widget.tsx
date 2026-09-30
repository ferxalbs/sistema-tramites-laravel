import { Link, usePage } from '@inertiajs/react';
import { Bot, MessageCircle, Send, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as supportIndex } from '@/routes/support';

type SupportProps = {
    assistant_topics: Record<string, { question: string; answer: string }>;
    whatsapp_url: string | null;
};

type ChatMessage = {
    id: number;
    role: 'assistant' | 'user';
    text: string;
};

const quickQuestionKeys = [
    'consultar-tramite',
    'estado-observado',
    'recibir-documento',
    'login',
];

const topicKeywords: Record<string, string[]> = {
    'consultar-tramite': ['tramite', 'expediente', 'seguimiento', 'consultar', 'consulta', 'estado'],
    'estado-observado': ['observado', 'observacion', 'subsanar', 'corregir', 'correccion'],
    'estado-revision': ['revision', 'revisando', 'asignado', 'evaluando'],
    'recibir-documento': ['recibir', 'entrega', 'descargar', 'documento', 'emitido', 'final'],
    password: ['contrasena', 'clave', 'olvide', 'recuperar', 'restablecer', 'password'],
    login: ['iniciar', 'sesion', 'entrar', 'acceder', 'ingresar', 'login', 'cuenta'],
    requisitos: ['requisito', 'documentos', 'documento', 'fut', 'presentar'],
    'corregir-observacion': ['corregir', 'observacion', 'subsanacion', 'subsanar'],
};

const stopWords = new Set(['como', 'que', 'para', 'por', 'una', 'uno', 'los', 'las', 'del', 'con', 'mi', 'me', 'el', 'la', 'un']);

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase();
}

function findTopicKey(question: string, topics: SupportProps['assistant_topics']): string | null {
    const normalizedQuestion = normalize(question);
    const words = new Set(
        (normalizedQuestion.match(/[a-z0-9]+/g) ?? []).filter((word) => !stopWords.has(word)),
    );

    let bestKey: string | null = null;
    let bestScore = 0;

    for (const [key, topic] of Object.entries(topics)) {
        const topicWords = new Set([
            ...(normalize(topic.question).match(/[a-z0-9]+/g) ?? []),
            ...(topicKeywords[key] ?? []),
        ].filter((word) => !stopWords.has(word)));
        const score = [...words].filter((word) => topicWords.has(word)).length;

        if (score > bestScore) {
            bestKey = key;
            bestScore = score;
        }
    }

    return bestKey;
}

export default function ChatbotWidget() {
    const { support } = usePage<{ support: SupportProps }>().props;
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            id: 0,
            role: 'assistant',
            text: 'Hola, soy el asistente del Sistema de Trámites. Escribe tu pregunta o elige una opción para empezar.',
        },
    ]);
    const nextMessageId = useRef(1);
    const conversationEnd = useRef<HTMLDivElement>(null);

    useEffect(() => {
        conversationEnd.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, open]);

    function sendQuestion(question: string) {
        const text = question.trim();
        if (!text) return;

        const topicKey = findTopicKey(text, support.assistant_topics);
        let answer = topicKey
            ? support.assistant_topics[topicKey]?.answer
            : null;

        if (topicKey === 'whatsapp') {
            answer = support.whatsapp_url
                ? 'Puedo conectarte con una persona del equipo. Usa el botón de WhatsApp debajo del chat cuando quieras.'
                : 'El canal de WhatsApp todavía no está disponible.';
        }

        answer ??= 'No encontré una respuesta para esa pregunta. Prueba con “consultar mi trámite”, “olvidé mi contraseña” o “corregir una observación”.';

        const userId = nextMessageId.current++;
        const assistantId = nextMessageId.current++;

        setMessages((current) => [
            ...current,
            { id: userId, role: 'user', text },
            { id: assistantId, role: 'assistant', text: answer },
        ]);
        setDraft('');
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        sendQuestion(draft);
    }

    const quickQuestions = quickQuestionKeys
        .map((key) => support.assistant_topics[key])
        .filter((topic): topic is { question: string; answer: string } => Boolean(topic));

    return (
        <div className="fixed right-4 bottom-20 z-50 flex flex-col items-end gap-3">
            {open && (
                <section
                    aria-label="Chatbot de orientación"
                    aria-live="polite"
                    className="flex h-[min(34rem,calc(100dvh-6rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-2xl"
                    role="dialog"
                >
                    <header className="flex items-center justify-between gap-3 bg-primary px-4 py-3 text-primary-foreground">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-foreground/15">
                                <Bot className="size-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <h2 className="truncate text-sm font-semibold">Chatbot de trámites</h2>
                                <p className="text-xs opacity-80">Orientación automática</p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            className="shrink-0 text-primary-foreground hover:bg-primary-foreground/15 hover:text-primary-foreground"
                            onClick={() => setOpen(false)}
                            aria-label="Cerrar chat"
                        >
                            <X />
                        </Button>
                    </header>

                    <div
                        className="flex-1 space-y-3 overflow-y-auto p-4"
                        role="log"
                        aria-label="Conversación con el asistente"
                        aria-live="polite"
                    >
                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <p
                                    className={`max-w-[88%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm leading-relaxed ${message.role === 'user'
                                        ? 'rounded-br-sm bg-primary text-primary-foreground'
                                        : 'rounded-bl-sm bg-muted text-foreground'
                                    }`}
                                >
                                    {message.text}
                                </p>
                            </div>
                        ))}

                        {messages.length === 1 && (
                            <div className="space-y-2 pt-1">
                                <p className="text-xs font-medium text-muted-foreground">Preguntas frecuentes</p>
                                {quickQuestions.map((topic) => (
                                    <button
                                        key={topic.question}
                                        type="button"
                                        className="block w-full rounded-xl border border-border px-3 py-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                        onClick={() => sendQuestion(topic.question)}
                                    >
                                        {topic.question}
                                    </button>
                                ))}
                            </div>
                        )}
                        <div ref={conversationEnd} />
                    </div>

                    {support.whatsapp_url && (
                        <a
                            href={support.whatsapp_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mx-4 mb-2 inline-flex items-center justify-center gap-2 rounded-full border border-border px-3 py-2 text-sm font-medium transition-colors hover:bg-accent"
                        >
                            <MessageCircle className="size-4" aria-hidden="true" />
                            Hablar con una persona por WhatsApp
                        </a>
                    )}

                    <form onSubmit={handleSubmit} className="flex items-center gap-2 border-t border-border p-3">
                        <Input
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            placeholder="Escribe tu pregunta..."
                            aria-label="Escribe tu pregunta"
                            maxLength={500}
                        />
                        <Button type="submit" size="icon" disabled={!draft.trim()} aria-label="Enviar pregunta">
                            <Send className="size-4" />
                        </Button>
                    </form>

                    <div className="flex items-center justify-between px-4 pb-3 text-xs text-muted-foreground">
                        <Link href={supportIndex()} className="underline underline-offset-4 hover:text-foreground">
                            Ver preguntas frecuentes
                        </Link>
                        <span>Orientación general</span>
                    </div>
                </section>
            )}

            <Button
                type="button"
                className="rounded-full shadow-lg"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-label={open ? 'Cerrar chatbot de trámites' : 'Abrir chatbot de trámites'}
            >
                {open ? <X data-icon="inline-start" /> : <MessageCircle data-icon="inline-start" />}
                {open ? 'Cerrar chatbot' : 'Chatbot'}
            </Button>
        </div>
    );
}
