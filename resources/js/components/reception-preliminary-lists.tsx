import { Plus, X } from 'lucide-react';
import { useRef, useState } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Kind = 'personas_relacionadas' | 'destinatarios' | 'personas_mencionadas';
type Entry = {
    nombres: string;
    apellidos: string | null;
    dni?: string | null;
    cargo_funcion?: string | null;
    tipo_relacion?: string;
    cargo_institucional_id?: number | null;
    cargo_texto?: string | null;
    correo_institucional?: string | null;
    descripcion?: string | null;
};
type Row = Entry & { id: number };
type Errors = Record<string, string>;

const sections: Array<{ kind: Kind; title: string; description: string }> = [
    {
        kind: 'personas_relacionadas',
        title: 'Personas relacionadas',
        description: 'Otras personas vinculadas al expediente, además del solicitante.',
    },
    {
        kind: 'destinatarios',
        title: 'Destinatarios preliminares',
        description: 'Personas a quienes podría dirigirse la respuesta. Se confirmarán al preparar el borrador.',
    },
    {
        kind: 'personas_mencionadas',
        title: 'Personas mencionadas',
        description: 'Otras personas citadas en el documento recibido.',
    },
];

export default function ReceptionPreliminaryLists({
    initial,
    errors,
    tiposRelacion,
    cargos,
    requisitos,
}: {
    initial: Record<Kind, Entry[]>;
    errors: Errors;
    tiposRelacion: Record<string, string>;
    cargos: Array<{ id: number; nombre: string }>;
    requisitos: {
        personas_relacionadas: boolean;
        destinatarios_multiples: boolean;
    };
}) {
    const nextId = useRef(0);
    const [rows, setRows] = useState<Record<Kind, Row[]>>(() => ({
        personas_relacionadas: initial.personas_relacionadas.map((entry) => ({
            ...entry,
            id: nextId.current++,
        })),
        destinatarios: initial.destinatarios.map((entry) => ({
            ...entry,
            id: nextId.current++,
        })),
        personas_mencionadas: initial.personas_mencionadas.map((entry) => ({
            ...entry,
            id: nextId.current++,
        })),
    }));
    const [expanded, setExpanded] = useState<Record<Kind, boolean>>(() => ({
        personas_relacionadas: initial.personas_relacionadas.length > 0,
        destinatarios: initial.destinatarios.length > 0,
        personas_mencionadas: initial.personas_mencionadas.length > 0,
    }));
    const required: Record<Kind, boolean> = {
        personas_relacionadas: requisitos.personas_relacionadas,
        destinatarios: requisitos.destinatarios_multiples,
        personas_mencionadas: false,
    };
    const hasError = (kind: Kind) => Object.keys(errors).some((key) => key === kind || key.startsWith(`${kind}.`));
    const isOpen = (kind: Kind) => required[kind] || hasError(kind) || rows[kind].length > 0 || expanded[kind];

    function add(kind: Kind) {
        setRows((current) => ({
            ...current,
            [kind]: [
                ...current[kind],
                {
                    id: nextId.current++,
                    nombres: '',
                    apellidos: null,
                    tipo_relacion: 'otro',
                },
            ],
        }));
    }

    function remove(kind: Kind, id: number) {
        setRows((current) => ({
            ...current,
            [kind]: current[kind].filter((entry) => entry.id !== id),
        }));
    }

    function changeCargo(id: number, cargoId: number | null) {
        setRows((current) => ({
            ...current,
            destinatarios: current.destinatarios.map((entry) =>
                entry.id === id
                    ? { ...entry, cargo_institucional_id: cargoId }
                    : entry,
            ),
        }));
    }

    return sections.map(({ kind, title, description }) => (
        <Card key={kind}>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle>{title} {required[kind] ? '(obligatorio para este tipo)' : '(opcional)'}</CardTitle>
                    {isOpen(kind) && <CardDescription>
                        {description}
                        {kind === 'personas_relacionadas' && requisitos.personas_relacionadas && ' Se exige al menos una.'}
                        {kind === 'destinatarios' && requisitos.destinatarios_multiples && ' Se exigen al menos dos.'}
                        {!required[kind] && ' Puede dejarse vacío.'}
                    </CardDescription>}
                </div>
                {!required[kind] && !hasError(kind) && rows[kind].length === 0 && (
                    <Button type="button" variant="outline" size="sm" aria-expanded={isOpen(kind)} onClick={() => setExpanded((current) => ({ ...current, [kind]: !current[kind] }))}>
                        {isOpen(kind) ? 'Ocultar' : 'Mostrar'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className={isOpen(kind) ? 'space-y-4' : 'hidden'}>
                {rows[kind].map((entry, index) => {
                    const prefix = `${kind}[${index}]`;
                    const errorPrefix = `${kind}.${index}`;
                    return (
                        <div
                            key={entry.id}
                            className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                        >
                            <EntryField
                                label="Nombres"
                                id={`${kind}-${entry.id}-nombres`}
                                error={errors[`${errorPrefix}.nombres`]}
                            >
                                <Input
                                    id={`${kind}-${entry.id}-nombres`}
                                    name={`${prefix}[nombres]`}
                                    defaultValue={entry.nombres}
                                    required
                                    minLength={2}
                                    maxLength={120}
                                />
                            </EntryField>
                            <EntryField
                                label="Apellidos"
                                id={`${kind}-${entry.id}-apellidos`}
                                error={errors[`${errorPrefix}.apellidos`]}
                            >
                                <Input
                                    id={`${kind}-${entry.id}-apellidos`}
                                    name={`${prefix}[apellidos]`}
                                    defaultValue={entry.apellidos ?? ''}
                                    maxLength={120}
                                />
                            </EntryField>
                            {kind !== 'destinatarios' && (
                                <>
                                    <EntryField
                                        label="DNI"
                                        id={`${kind}-${entry.id}-dni`}
                                        error={errors[`${errorPrefix}.dni`]}
                                    >
                                        <Input
                                            id={`${kind}-${entry.id}-dni`}
                                            name={`${prefix}[dni]`}
                                            defaultValue={entry.dni ?? ''}
                                            inputMode="numeric"
                                            pattern="[0-9]{8}"
                                            maxLength={8}
                                        />
                                    </EntryField>
                                    <EntryField
                                        label="Cargo o función"
                                        id={`${kind}-${entry.id}-cargo`}
                                        error={
                                            errors[
                                                `${errorPrefix}.cargo_funcion`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`${kind}-${entry.id}-cargo`}
                                            name={`${prefix}[cargo_funcion]`}
                                            defaultValue={
                                                entry.cargo_funcion ?? ''
                                            }
                                            maxLength={160}
                                        />
                                    </EntryField>
                                </>
                            )}
                            {kind === 'personas_relacionadas' && (
                                <EntryField
                                    label="Tipo de relación"
                                    id={`${kind}-${entry.id}-tipo`}
                                    error={
                                        errors[`${errorPrefix}.tipo_relacion`]
                                    }
                                >
                                    <Select
                                        name={`${prefix}[tipo_relacion]`}
                                        defaultValue={
                                            entry.tipo_relacion ?? 'otro'
                                        }
                                        items={Object.entries(
                                            tiposRelacion,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                    >
                                        <SelectTrigger
                                            id={`${kind}-${entry.id}-tipo`}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {Object.entries(
                                                    tiposRelacion,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </EntryField>
                            )}
                            {kind === 'destinatarios' && (
                                <>
                                    <EntryField
                                        label="Cargo institucional"
                                        id={`${kind}-${entry.id}-cargo-id`}
                                        error={
                                            errors[
                                                `${errorPrefix}.cargo_institucional_id`
                                            ]
                                        }
                                    >
                                        <Select
                                            name={`${prefix}[cargo_institucional_id]`}
                                            value={
                                                entry.cargo_institucional_id
                                                    ? String(
                                                          entry.cargo_institucional_id,
                                                      )
                                                    : null
                                            }
                                            onValueChange={(value) =>
                                                changeCargo(
                                                    entry.id,
                                                    value
                                                        ? Number(value)
                                                        : null,
                                                )
                                            }
                                            items={cargos.map((cargo) => ({
                                                value: String(cargo.id),
                                                label: cargo.nombre,
                                            }))}
                                        >
                                            <SelectTrigger
                                                id={`${kind}-${entry.id}-cargo-id`}
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Sin cargo de catálogo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {cargos.map((cargo) => (
                                                        <SelectItem
                                                            key={cargo.id}
                                                            value={String(
                                                                cargo.id,
                                                            )}
                                                        >
                                                            {cargo.nombre}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {entry.cargo_institucional_id && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    changeCargo(entry.id, null)
                                                }
                                            >
                                                Quitar cargo de catálogo
                                            </Button>
                                        )}
                                    </EntryField>
                                    <EntryField
                                        label="Cargo libre"
                                        id={`${kind}-${entry.id}-cargo-texto`}
                                        error={
                                            errors[`${errorPrefix}.cargo_texto`]
                                        }
                                    >
                                        <Input
                                            id={`${kind}-${entry.id}-cargo-texto`}
                                            name={`${prefix}[cargo_texto]`}
                                            defaultValue={
                                                entry.cargo_texto ?? ''
                                            }
                                            maxLength={160}
                                        />
                                    </EntryField>
                                    <EntryField
                                        label="Correo institucional"
                                        id={`${kind}-${entry.id}-correo`}
                                        error={
                                            errors[
                                                `${errorPrefix}.correo_institucional`
                                            ]
                                        }
                                    >
                                        <Input
                                            id={`${kind}-${entry.id}-correo`}
                                            name={`${prefix}[correo_institucional]`}
                                            type="email"
                                            defaultValue={
                                                entry.correo_institucional ?? ''
                                            }
                                            maxLength={190}
                                        />
                                    </EntryField>
                                </>
                            )}
                            {kind === 'personas_mencionadas' && (
                                <EntryField
                                    label="Descripción"
                                    id={`${kind}-${entry.id}-descripcion`}
                                    error={errors[`${errorPrefix}.descripcion`]}
                                >
                                    <Input
                                        id={`${kind}-${entry.id}-descripcion`}
                                        name={`${prefix}[descripcion]`}
                                        defaultValue={entry.descripcion ?? ''}
                                        maxLength={255}
                                    />
                                </EntryField>
                            )}
                            <div className="flex items-end justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => remove(kind, entry.id)}
                                >
                                    <X data-icon="inline-start" /> Quitar
                                </Button>
                            </div>
                        </div>
                    );
                })}
                <InputError message={errors[kind]} />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => add(kind)}
                >
                    <Plus data-icon="inline-start" /> Agregar
                </Button>
            </CardContent>
        </Card>
    ));
}

function EntryField({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
