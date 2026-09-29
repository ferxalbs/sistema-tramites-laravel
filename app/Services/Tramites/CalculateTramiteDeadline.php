<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class CalculateTramiteDeadline
{
    /**
     * @return array{fecha_estimada: string, fecha_maxima: string, dias_restantes: int, alerta: string, etiqueta: string}|null
     */
    public function forTramite(Tramite $tramite, ?DateTimeImmutable $today = null): ?array
    {
        $configuration = DB::table('configuracion_plazos as c')
            ->join('tipos_tramite as t', 't.id', '=', 'c.tipo_tramite_id')
            ->where('t.codigo', $tramite->tipo_documento)
            ->where('c.activo', true)
            ->first(['c.dias_estimados', 'c.dias_maximos', 'c.tipo_dias', 'c.dias_anticipacion_recordatorio', 'c.es_plazo_oficial']);

        if ($configuration === null) {
            return null;
        }

        $holidays = array_fill_keys(DB::table('feriados')->where('activo', true)
            ->where('es_demostracion', false)->pluck('fecha')->all(), true);
        $arrival = $tramite->getRawOriginal('fecha_llegada_oficina') ?? $tramite->getRawOriginal('fecha_recepcion');
        if (! is_string($arrival)) {
            return null;
        }

        $start = new DateTimeImmutable(substr($arrival, 0, 10));
        $current = $today ?? new DateTimeImmutable(now()->toDateString());
        $business = $configuration->tipo_dias === 'habiles';
        $estimated = $this->addDays($start, (int) $configuration->dias_estimados, $business, $holidays);
        $maximum = $this->addDays($start, (int) $configuration->dias_maximos, $business, $holidays);
        $remaining = $this->difference($current, $maximum, $business, $holidays);
        $alert = match (true) {
            in_array($tramite->estado, ['entregado', 'cerrado'], true) => 'completado',
            $remaining < 0 => 'vencido',
            $remaining === 0 => 'hoy',
            $remaining <= (int) $configuration->dias_anticipacion_recordatorio => 'proximo',
            default => 'normal',
        };

        return [
            'fecha_estimada' => $estimated->format('Y-m-d'),
            'fecha_maxima' => $maximum->format('Y-m-d'),
            'dias_restantes' => $remaining,
            'alerta' => $alert,
            'etiqueta' => (bool) $configuration->es_plazo_oficial ? 'Plazo oficial' : 'Plazo estimado referencial',
        ];
    }

    /** @param array<string, bool> $holidays */
    private function addDays(DateTimeImmutable $start, int $days, bool $business, array $holidays): DateTimeImmutable
    {
        $date = $start;
        $added = 0;
        while ($added < $days) {
            $date = $date->modify('+1 day');
            if (! $business || ((int) $date->format('N') < 6 && ! isset($holidays[$date->format('Y-m-d')]))) {
                $added++;
            }
        }

        return $date;
    }

    /** @param array<string, bool> $holidays */
    private function difference(DateTimeImmutable $from, DateTimeImmutable $to, bool $business, array $holidays): int
    {
        if (! $business) {
            return (int) $from->diff($to)->format('%r%a');
        }

        $sign = $from <= $to ? 1 : -1;
        if ($sign < 0) {
            [$from, $to] = [$to, $from];
        }

        $days = 0;
        while ($from < $to) {
            $from = $from->modify('+1 day');
            if ((int) $from->format('N') < 6 && ! isset($holidays[$from->format('Y-m-d')])) {
                $days++;
            }
        }

        return $sign * $days;
    }
}
