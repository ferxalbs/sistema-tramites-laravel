<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuditLogController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        $tramiteEvents = DB::table('tramite_eventos as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.usuario_id')
            ->selectRaw("events.id as id, 0 as origen_orden, events.created_at as fecha, COALESCE(actors.name, 'Sistema') as actor, events.accion, 'tramites' as modulo, 'expediente' as entidad, events.tramite_id as entidad_id, 'exitoso' as resultado");

        $accountEvents = DB::table('user_account_events as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->selectRaw("events.id as id, 1 as origen_orden, events.created_at as fecha, COALESCE(actors.name, 'Sistema') as actor, events.accion, 'cuentas' as modulo, 'usuario' as entidad, events.user_id as entidad_id, CASE WHEN events.accion = 'reset_link' AND events.motivo = 'El correo no pudo enviarse.' THEN 'envio_no_disponible' ELSE 'exitoso' END as resultado");

        $holidayEvents = DB::table('feriado_eventos as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->selectRaw("events.id as id, 2 as origen_orden, events.created_at as fecha, COALESCE(actors.name, 'Sistema') as actor, events.accion, 'feriados' as modulo, 'feriado' as entidad, events.feriado_id as entidad_id, 'exitoso' as resultado");

        $deliveryConfigEvents = DB::table('tramite_config_events as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->selectRaw("events.id as id, 3 as origen_orden, events.created_at as fecha, COALESCE(actors.name, 'Sistema') as actor, events.accion, 'entregas' as modulo, events.entidad, events.entidad_id, 'exitoso' as resultado");

        $reportExportEvents = DB::table('tramite_report_export_events as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->selectRaw("events.id as id, 4 as origen_orden, events.created_at as fecha, COALESCE(actors.name, 'Sistema') as actor, 'exportar_reporte_csv' as accion, 'reportes' as modulo, 'exportacion' as entidad, events.id as entidad_id, 'exitoso' as resultado");

        $events = DB::query()
            ->fromSub($tramiteEvents->unionAll($accountEvents)->unionAll($holidayEvents)->unionAll($deliveryConfigEvents)->unionAll($reportExportEvents), 'auditoria')
            ->select(['id', 'fecha', 'actor', 'accion', 'modulo', 'entidad', 'entidad_id', 'resultado'])
            ->orderByDesc('fecha')
            ->orderByDesc('origen_orden')
            ->orderByDesc('id')
            ->paginate(25);

        return Inertia::render('auditoria', ['events' => $events]);
    }
}
