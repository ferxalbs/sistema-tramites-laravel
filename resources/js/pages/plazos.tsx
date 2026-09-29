import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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
import { update } from '@/routes/admin/deadlines';

type Deadline = {
    id: number;
    codigo: string;
    tipo: string;
    dias_estimados: number;
    dias_maximos: number;
    tipo_dias: string;
    dias_anticipacion_recordatorio: number;
    activo: boolean | number;
};

type Holiday = {
    fecha: string;
    nombre: string;
    es_demostracion: boolean | number;
};

export default function Plazos({
    plazos,
    feriados,
}: {
    plazos: Deadline[];
    feriados: Holiday[];
}) {
    return (
        <>
            <Head title="Plazos referenciales" />
            <main>
                <Card>
                    <CardHeader>
                        <CardTitle>Plazos referenciales</CardTitle>
                        <CardDescription>
                            Estos valores son estimaciones, no plazos oficiales.
                            El cálculo hábil omite sábados, domingos y feriados
                            confirmados.
                        </CardDescription>
                    </CardHeader>
                </Card>
                {plazos.map((plazo) => (
                    <Card key={plazo.id}>
                        <CardHeader>
                            <CardTitle>{plazo.tipo}</CardTitle>
                            <CardDescription>
                                {plazo.codigo}
                                {plazo.activo ? '' : ' · Inactivo'}
                            </CardDescription>
                        </CardHeader>
                        <Form
                            {...update.form({ deadline: plazo.id })}
                            disableWhileProcessing
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label
                                                htmlFor={`estimado-${plazo.id}`}
                                            >
                                                Días estimados
                                            </Label>
                                            <Input
                                                id={`estimado-${plazo.id}`}
                                                name="dias_estimados"
                                                type="number"
                                                min={1}
                                                max={365}
                                                required
                                                defaultValue={
                                                    plazo.dias_estimados
                                                }
                                            />
                                            <InputError
                                                message={errors.dias_estimados}
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`maximo-${plazo.id}`}
                                            >
                                                Días máximos
                                            </Label>
                                            <Input
                                                id={`maximo-${plazo.id}`}
                                                name="dias_maximos"
                                                type="number"
                                                min={1}
                                                max={365}
                                                required
                                                defaultValue={
                                                    plazo.dias_maximos
                                                }
                                            />
                                            <InputError
                                                message={errors.dias_maximos}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor={`modo-${plazo.id}`}>
                                                Tipo de días
                                            </Label>
                                            <Select
                                                name="tipo_dias"
                                                defaultValue={plazo.tipo_dias}
                                                items={[
                                                    {
                                                        value: 'calendario',
                                                        label: 'Calendario',
                                                    },
                                                    {
                                                        value: 'habiles',
                                                        label: 'Hábiles',
                                                    },
                                                ]}
                                                required
                                            >
                                                <SelectTrigger
                                                    id={`modo-${plazo.id}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="calendario">
                                                            Calendario
                                                        </SelectItem>
                                                        <SelectItem value="habiles">
                                                            Hábiles
                                                        </SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.tipo_dias}
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`aviso-${plazo.id}`}
                                            >
                                                Días de anticipación
                                            </Label>
                                            <Input
                                                id={`aviso-${plazo.id}`}
                                                name="dias_anticipacion_recordatorio"
                                                type="number"
                                                min={0}
                                                max={365}
                                                required
                                                defaultValue={
                                                    plazo.dias_anticipacion_recordatorio
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.dias_anticipacion_recordatorio
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Guardar
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    </Card>
                ))}
                <Card>
                    <CardHeader>
                        <CardTitle>Feriados activos</CardTitle>
                        <CardDescription>
                            Las fechas de demostración no afectan el cálculo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {feriados.length === 0
                            ? 'No hay feriados confirmados.'
                            : feriados.map((feriado) => (
                                  <p key={feriado.fecha}>
                                      {feriado.fecha} · {feriado.nombre}
                                      {feriado.es_demostracion
                                          ? ' (demostración)'
                                          : ''}
                                  </p>
                              ))}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
