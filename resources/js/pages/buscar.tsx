import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TramiteAsignacionController from '@/actions/App/Http/Controllers/TramiteAsignacionController';
import TramiteController from '@/actions/App/Http/Controllers/TramiteController';
import TramiteEstudianteController from '@/actions/App/Http/Controllers/TramiteEstudianteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { edit as adminUserEdit } from '@/routes/admin/users';
import { index as searchIndex } from '@/routes/search';

type Expediente = {
    id: number;
    codigo: string;
    asunto: string;
    estado: string;
    tipo_documento: string;
};

type Person = { id: number; name: string; rol: string };
type Document = {
    id: number;
    tramite_id: number;
    numero: string;
    tipo_documento: string;
};

type Props = {
    query: string;
    status: 'empty' | 'short' | 'too_long' | 'ok';
    reviewer: boolean;
    student: boolean;
    results: {
        expedientes: Expediente[];
        personas: Person[];
        documentos: Document[];
    };
};

export default function Buscar({
    query,
    status,
    reviewer,
    student,
    results,
}: Props) {
    const [text, setText] = useState(query);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            searchIndex(),
            { q: text },
            { preserveState: true, replace: true },
        );
    }

    const noResults =
        status === 'ok' &&
        results.expedientes.length === 0 &&
        results.personas.length === 0 &&
        results.documentos.length === 0;

    return (
        <>
            <Head title="Búsqueda interna" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Búsqueda interna</h1>
                    <p className="text-sm text-muted-foreground">
                        Consulte expedientes y los registros permitidos para su
                        rol.
                    </p>
                </div>
                <Card>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <label htmlFor="global-query" className="sr-only">
                                Buscar en el sistema
                            </label>
                            <Input
                                id="global-query"
                                value={text}
                                onChange={(event) =>
                                    setText(event.target.value)
                                }
                                placeholder="Código, DNI, referencia física, asunto, persona o documento"
                                className="flex-1"
                            />
                            <Button type="submit">
                                <Search data-icon="inline-start" /> Buscar
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {status === 'short' && <p>Ingrese al menos dos caracteres.</p>}
                {status === 'too_long' && (
                    <p>La búsqueda admite hasta 80 caracteres.</p>
                )}
                {noResults && <p>No se encontraron resultados.</p>}
                {status === 'ok' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Expedientes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="divide-y">
                                    {results.expedientes.map((expediente) => (
                                        <li
                                            key={expediente.id}
                                            className="py-3 first:pt-0"
                                        >
                                            <Link
                                                className="font-medium text-primary hover:underline"
                                                href={
                                                    reviewer
                                                        ? TramiteAsignacionController.docenteShow(
                                                              {
                                                                  tramite:
                                                                      expediente.id,
                                                              },
                                                          )
                                                        : student
                                                          ? TramiteEstudianteController.show(
                                                                {
                                                                    tramite:
                                                                        expediente.id,
                                                                },
                                                            )
                                                          : TramiteController.show(
                                                                {
                                                                    tramite:
                                                                        expediente.id,
                                                                },
                                                            )
                                                }
                                            >
                                                {expediente.codigo}
                                            </Link>
                                            <p>{expediente.asunto}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {expediente.tipo_documento} ·{' '}
                                                {expediente.estado}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                        {!reviewer && !student && (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Personas</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="divide-y">
                                            {results.personas.map((person) => (
                                                <li
                                                    key={person.id}
                                                    className="py-3 first:pt-0"
                                                >
                                                    <Link
                                                        className="font-medium text-primary hover:underline"
                                                        href={adminUserEdit({
                                                            user: person.id,
                                                        })}
                                                    >
                                                        {person.name}
                                                    </Link>
                                                    <p className="text-sm text-muted-foreground">
                                                        {person.rol}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Documentos oficiales
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="divide-y">
                                            {results.documentos.map(
                                                (document) => (
                                                    <li
                                                        key={document.id}
                                                        className="py-3 first:pt-0"
                                                    >
                                                        <Link
                                                            className="font-medium text-primary hover:underline"
                                                            href={TramiteController.show(
                                                                {
                                                                    tramite:
                                                                        document.tramite_id,
                                                                },
                                                            )}
                                                        >
                                                            {document.numero}
                                                        </Link>
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                document.tipo_documento
                                                            }
                                                        </p>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </div>
                )}
            </main>
        </>
    );
}
