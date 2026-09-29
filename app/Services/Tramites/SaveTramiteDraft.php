<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramiteAsignacion;
use App\Models\TramiteBorrador;
use App\Models\TramiteEvento;
use App\Models\TramiteObservacionRevision;
use App\Models\TramitePlantilla;
use App\Models\TramiteRespuestaObservacion;
use App\Models\TramiteRondaRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaveTramiteDraft
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function execute(Tramite $tramite, array $datos, User $actor): TramiteBorrador
    {
        $plantilla = TramitePlantilla::query()
            ->whereKey($datos['plantilla_id'])
            ->where('estado', 'publicada')
            ->where('activa', true)
            ->first();

        if ($plantilla === null) {
            throw ValidationException::withMessages(['plantilla_id' => 'La plantilla ya no está disponible.']);
        }

        $preparar = (bool) $datos['preparar'];
        $destinatarios = $this->destinatarios($datos['destinatarios'] ?? []);
        $personas = $this->personasMencionadas($datos['personas_mencionadas'] ?? []);
        $adjuntos = $this->adjuntos($tramite, $datos['adjuntos'] ?? []);
        $this->validarBorrador($tramite, $plantilla, $datos, $destinatarios, $preparar);

        return DB::transaction(function () use ($tramite, $plantilla, $datos, $actor, $preparar, $destinatarios, $personas, $adjuntos): TramiteBorrador {
            $campos = $this->camposEditables($tramite, $plantilla, $datos, $destinatarios, $personas, $adjuntos, (array) ($datos['campos'] ?? []), $preparar);
            if (! DB::table('tipos_documento_salida')
                ->where('codigo', $plantilla->tipo_documento_salida)
                ->where('activo', true)
                ->exists()) {
                throw ValidationException::withMessages(['plantilla_id' => 'El formato documental de la plantilla ya no está disponible.']);
            }

            if ($plantilla->tipo_documento_salida === 'memorando') {
                if ($plantilla->modalidad === null || ! DB::table('modalidades_documento')
                    ->where('tipo_documento_salida', 'memorando')
                    ->where('codigo', $plantilla->modalidad)
                    ->where('activo', true)
                    ->exists()) {
                    throw ValidationException::withMessages(['plantilla_id' => 'La plantilla de Memorando no tiene modalidad válida.']);
                }
            } elseif ($plantilla->modalidad !== null) {
                throw ValidationException::withMessages(['plantilla_id' => 'La plantilla de Informe no admite modalidad.']);
            }

            $estadoActual = DB::table('tramites')->where('id', $tramite->id)->value('estado');

            abort_unless(in_array($estadoActual, ['digitalizado', 'borrador_preparado'], true), 409);
            $anterior = TramiteBorrador::query()
                ->where('tramite_id', $tramite->id)
                ->where('es_actual', true)
                ->first();
            $borrador = $this->crearVersion($tramite, $plantilla, $datos, $actor, $preparar, $destinatarios, $personas, $adjuntos, $anterior, null, $campos);

            DB::table('tramites')->where('id', $tramite->id)->update([
                'formato_salida' => $plantilla->tipo_documento_salida,
                'modalidad_documento' => $plantilla->modalidad,
                'updated_at' => now(),
            ]);

            $estadoNuevo = $estadoActual;

            if ($preparar && $estadoActual === 'digitalizado') {
                $actualizados = DB::table('tramites')
                    ->where('id', $tramite->id)
                    ->where('estado', 'digitalizado')
                    ->update([
                        'estado' => 'borrador_preparado',
                        'updated_at' => now(),
                    ]);

                abort_unless($actualizados === 1, 409);
                $estadoNuevo = 'borrador_preparado';
            }

            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $actor->id,
                'accion' => $preparar ? 'borrador_preparado' : 'borrador_guardado',
                'descripcion' => $preparar
                    ? 'Se preparó la versión '.$borrador->version.' del borrador para revisión.'
                    : 'Se guardó la versión '.$borrador->version.' del borrador.',
                'estado_anterior' => $estadoNuevo === $estadoActual ? null : $estadoActual,
                'estado_nuevo' => $estadoNuevo === $estadoActual ? null : $estadoNuevo,
                'metadatos' => [
                    'borrador_id' => $borrador->id,
                    'version' => $borrador->version,
                    'plantilla' => $plantilla->codigo,
                ],
            ]);

            return $borrador;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function correct(Tramite $tramite, array $datos, User $actor): TramiteBorrador
    {
        abort_unless($actor->activo && $actor->rol === 'asistente', 403);

        $plantilla = TramitePlantilla::query()->whereKey($datos['plantilla_id'])->first();

        if ($plantilla === null) {
            throw ValidationException::withMessages(['plantilla_id' => 'La plantilla del borrador ya no está disponible.']);
        }

        $destinatarios = $this->destinatarios($datos['destinatarios'] ?? []);
        $personas = $this->personasMencionadas($datos['personas_mencionadas'] ?? []);
        $adjuntos = $this->adjuntos($tramite, $datos['adjuntos'] ?? []);
        $this->validarBorrador($tramite, $plantilla, $datos, $destinatarios, true);
        $resumen = trim((string) $datos['resumen_correccion']);

        if (mb_strlen($resumen) < 8) {
            throw ValidationException::withMessages(['resumen_correccion' => 'El resumen de las correcciones debe tener al menos 8 caracteres.']);
        }

        return DB::transaction(function () use ($tramite, $datos, $actor, $plantilla, $destinatarios, $personas, $adjuntos, $resumen): TramiteBorrador {
            $campos = $this->camposEditables($tramite, $plantilla, $datos, $destinatarios, $personas, $adjuntos, (array) ($datos['campos'] ?? []), true);
            abort_unless(DB::table('tramites')->where('id', $tramite->id)->where('estado', 'observado')->exists(), 409);

            $anterior = TramiteBorrador::query()
                ->where('tramite_id', $tramite->id)
                ->where('es_actual', true)
                ->where('estado', 'preparado_asignacion')
                ->first();

            abort_unless($anterior !== null && $anterior->plantilla_id === $plantilla->id, 409);

            $asignacion = TramiteAsignacion::query()
                ->where('tramite_id', $tramite->id)
                ->where('activa', true)
                ->first();

            abort_unless($asignacion !== null, 409);

            $ronda = TramiteRondaRevision::query()
                ->where('tramite_id', $tramite->id)
                ->where('asignacion_id', $asignacion->id)
                ->where('estado', 'observada')
                ->orderByDesc('numero_ronda')
                ->first();

            abort_unless($ronda !== null, 409);

            $observaciones = TramiteObservacionRevision::query()
                ->where('ronda_id', $ronda->id)
                ->orderBy('orden')
                ->get();
            $respuestas = $this->validarRespuestas($observaciones, $datos['respuestas'] ?? []);
            $borrador = $this->crearVersion(
                $tramite,
                $plantilla,
                $datos,
                $actor,
                true,
                $destinatarios,
                $personas,
                $adjuntos,
                $anterior,
                $anterior->version_plantilla,
                $campos,
            );

            foreach ($respuestas as $respuesta) {
                TramiteRespuestaObservacion::query()->create([
                    'observacion_id' => $respuesta['observacion_id'],
                    'borrador_id' => $borrador->id,
                    'asistente_id' => $actor->id,
                    'respuesta' => $respuesta['respuesta'],
                ]);
            }

            $rondaActualizada = TramiteRondaRevision::query()
                ->whereKey($ronda->id)
                ->where('estado', 'observada')
                ->where('activa', false)
                ->update([
                    'estado' => 'corregida',
                    'resumen_correccion' => $resumen,
                    'updated_at' => now(),
                ]);

            abort_unless($rondaActualizada === 1, 409);

            $actualizados = DB::table('tramites')
                ->where('id', $tramite->id)
                ->where('estado', 'observado')
                ->update(['estado' => 'corregido', 'updated_at' => now()]);

            abort_unless($actualizados === 1, 409);

            TramiteEvento::query()->create([
                'tramite_id' => $tramite->id,
                'usuario_id' => $actor->id,
                'accion' => 'correccion_reenvio',
                'descripcion' => 'La corrección se guardó en una versión nueva y volvió al mismo revisor.',
                'estado_anterior' => 'observado',
                'estado_nuevo' => 'corregido',
                'metadatos' => [
                    'ronda_id' => $ronda->id,
                    'version_anterior' => $anterior->version,
                    'version_nueva' => $borrador->version,
                    'respuestas' => count($respuestas),
                ],
            ]);

            return $borrador;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>  $destinatarios
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string}>  $personas
     * @param  array<int, array{id: int, nombre: string, categoria: string, version: int}>  $adjuntos
     * @param  array<int, array{id: int, clave: string, valor: string}>  $campos
     */
    private function crearVersion(Tramite $tramite, TramitePlantilla $plantilla, array $datos, User $actor, bool $preparar, array $destinatarios, array $personas, array $adjuntos, ?TramiteBorrador $anterior, ?int $versionPlantilla = null, array $campos = []): TramiteBorrador
    {
        $ahora = now()->toDateTimeString();
        DB::table('tramite_borrador_secuencias')->insertOrIgnore([
            'tramite_id' => $tramite->id,
            'ultimo_numero' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $secuencia = DB::selectOne(
            'UPDATE tramite_borrador_secuencias SET ultimo_numero = ultimo_numero + 1, updated_at = ? WHERE tramite_id = ? RETURNING ultimo_numero',
            [$ahora, $tramite->id],
        );

        if ($secuencia === null) {
            throw new RuntimeException('No se pudo reservar una versión para el borrador.');
        }

        $anterior?->update([
            'es_actual' => false,
            'estado' => 'obsoleto',
        ]);

        $borrador = TramiteBorrador::query()->create([
            'tramite_id' => $tramite->id,
            'plantilla_id' => $plantilla->id,
            'version_plantilla' => $versionPlantilla ?? $plantilla->version,
            'contenido_plantilla_snapshot' => $plantilla->contenido,
            'contenido_renderizado' => $this->renderizarPlantilla($tramite, $plantilla, $datos, $destinatarios, $personas, $adjuntos, $campos),
            'version' => (int) $secuencia->ultimo_numero,
            'remitente_id' => $datos['remitente_id'] ?? null,
            'firmante_id' => $datos['firmante_id'] ?? null,
            'fecha_documento' => $datos['fecha_documento'],
            'lugar' => trim($datos['lugar']),
            'asunto' => trim($datos['asunto']),
            'introduccion' => $this->textoOpcional($datos['introduccion'] ?? null),
            'contenido_principal' => $this->textoOpcional($datos['contenido_principal'] ?? null),
            'cierre' => $this->textoOpcional($datos['cierre'] ?? null),
            'destinatarios' => $destinatarios,
            'personas_mencionadas' => $personas,
            'adjuntos' => $adjuntos,
            'estado' => $preparar ? 'preparado_asignacion' : ($anterior === null ? 'incompleto' : 'en_edicion'),
            'es_actual' => true,
            'preparado_en' => $preparar ? now() : null,
            'creado_por' => $actor->id,
        ]);

        if ($campos !== []) {
            DB::table('tramite_borrador_valores')->insert(array_map(static fn (array $campo): array => [
                'borrador_id' => $borrador->id,
                'campo_id' => $campo['id'],
                'usuario_id' => $actor->id,
                'valor' => $campo['valor'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $campos));
        }

        return $borrador;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>  $destinatarios
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string}>  $personas
     * @param  array<int, array{id: int, nombre: string, categoria: string, version: int}>  $adjuntos
     * @param  array<int, array{id: int, clave: string, valor: string}>  $campos
     */
    private function renderizarPlantilla(Tramite $tramite, TramitePlantilla $plantilla, array $datos, array $destinatarios, array $personas, array $adjuntos, array $campos): string
    {
        $variables = $this->valoresAutomaticos($tramite, $datos, $destinatarios, $personas, $adjuntos);
        $variables['ENCABEZADO_INSTITUCIONAL'] = (string) config('app.name');
        foreach ($campos as $campo) {
            $variables[$campo['clave']] = $campo['valor'];
        }
        $variables['DESTINATARIO'] = $variables['DESTINATARIO_NOMBRE'];
        $variables['DESTINATARIOS'] = $variables['LISTA_DESTINATARIOS'];
        $variables['REMITENTE'] = $variables['REMITENTE_NOMBRE'];
        $variables['FIRMANTE'] = $variables['FIRMANTE_NOMBRE'];
        $variables['CONTENIDO'] = $variables['CONTENIDO_PRINCIPAL'];

        $contenido = preg_replace_callback('/\{\{([A-Z0-9_]+)\}\}/',
            static fn (array $match): string => htmlspecialchars((string) ($variables[$match[1]] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $plantilla->contenido) ?? '';
        $contenido = preg_replace('~<\s*(?:br\s*/?|/p|/div|/li|/section|/article|/header|/footer|/h[12])\s*>~i', "\n", $contenido) ?? $contenido;
        $contenido = html_entity_decode(strip_tags($contenido), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\n{3,}/', "\n\n", $contenido) ?? $contenido);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>  $destinatarios
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string}>  $personas
     * @param  array<int, array{id: int, nombre: string, categoria: string, version: int}>  $adjuntos
     * @param  array<string, mixed>  $entrada
     * @return array<int, array{id: int, clave: string, valor: string}>
     */
    private function camposEditables(Tramite $tramite, TramitePlantilla $plantilla, array $datos, array $destinatarios, array $personas, array $adjuntos, array $entrada, bool $preparar): array
    {
        $fijos = ['ASUNTO', 'LUGAR', 'FECHA', 'INTRODUCCION', 'CONTENIDO_PRINCIPAL', 'CIERRE'];
        $campos = DB::table('tramite_plantilla_campos')->where('plantilla_id', $plantilla->id)
            ->where('activo', true)->orderBy('orden')->orderBy('id')->get();
        $permitidos = $campos->filter(fn ($campo): bool => $campo->fuente_automatica === null
            && ! in_array($campo->clave_variable, $fijos, true))->pluck('clave_variable')->all();
        $automaticos = $this->valoresAutomaticos($tramite, $datos, $destinatarios, $personas, $adjuntos);

        foreach (array_keys($entrada) as $clave) {
            if (! in_array($clave, $permitidos, true)) {
                throw ValidationException::withMessages(['campos' => 'Un campo no pertenece a esta versión de plantilla o no se puede editar.']);
            }
        }

        $valores = [];

        foreach ($campos as $campo) {
            $editable = in_array($campo->clave_variable, $permitidos, true);
            $valor = trim(strip_tags((string) ($editable && array_key_exists($campo->clave_variable, $entrada)
                ? $entrada[$campo->clave_variable]
                : ($automaticos[$campo->clave_variable] ?? $campo->valor_predeterminado ?? ''))));
            $longitud = (int) ($campo->longitud_maxima ?? 60000);

            if (mb_strlen($valor) > $longitud) {
                if ($editable) {
                    throw ValidationException::withMessages(["campos.{$campo->clave_variable}" => "El campo no puede superar {$longitud} caracteres."]);
                }

                $valor = mb_substr($valor, 0, $longitud);
            }

            if ($preparar && $editable && $campo->obligatorio && $valor === '') {
                throw ValidationException::withMessages(["campos.{$campo->clave_variable}" => 'Este campo es obligatorio.']);
            }

            if ($valor !== '' && $campo->tipo_campo === 'numero' && ! is_numeric($valor)) {
                throw ValidationException::withMessages(["campos.{$campo->clave_variable}" => 'El campo debe ser numérico.']);
            }

            $fecha = $campo->tipo_campo === 'fecha' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;

            if ($valor !== '' && $campo->tipo_campo === 'fecha' && ($fecha === false || $fecha->format('Y-m-d') !== $valor)) {
                throw ValidationException::withMessages(["campos.{$campo->clave_variable}" => 'La fecha debe tener formato AAAA-MM-DD.']);
            }

            $valores[] = ['id' => (int) $campo->id, 'clave' => $campo->clave_variable, 'valor' => $valor];
        }

        return $valores;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>  $destinatarios
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string}>  $personas
     * @param  array<int, array{id: int, nombre: string, categoria: string, version: int}>  $adjuntos
     * @return array<string, string>
     */
    private function valoresAutomaticos(Tramite $tramite, array $datos, array $destinatarios, array $personas, array $adjuntos): array
    {
        $estudiante = DB::table('users')->where('id', $tramite->getAttribute('propietario_id'))
            ->where('rol', 'estudiante')->first(['id', 'name', 'dni']);
        $perfil = $estudiante === null ? null : DB::table('perfiles_estudiante')
            ->where('user_id', $estudiante->id)->first(['codigo_estudiante', 'ciclo_actual', 'anio_egreso']);
        $programa = DB::table('programas_estudio')->where('id', $tramite->getAttribute('programa_estudio_id'))
            ->value('nombre');
        $usuarios = DB::table('users as usuario')
            ->leftJoin('cargos_institucionales as cargo', 'cargo.id', '=', 'usuario.cargo_institucional_id')
            ->whereIn('usuario.id', array_filter([$datos['remitente_id'] ?? null, $datos['firmante_id'] ?? null]))
            ->get(['usuario.id', 'usuario.name', 'cargo.nombre as cargo'])->keyBy('id');
        $remitente = $usuarios->get($datos['remitente_id'] ?? 0);
        $firmante = $usuarios->get($datos['firmante_id'] ?? 0);
        $nombre = static fn (array $persona): string => trim(implode(' ', array_filter([
            $persona['nombres'], $persona['apellidos'],
        ])));
        $nombreConCargo = static fn (array $persona): string => $nombre($persona)
            .(($persona['cargo'] ?? null) ? ' — '.$persona['cargo'] : '');
        $destinatario = $destinatarios[0] ?? null;
        $lugar = trim(strip_tags((string) $datos['lugar']));
        $fecha = (string) $datos['fecha_documento'];
        $academicos = [];

        foreach ([
            'Estudiante' => $estudiante === null ? null : $estudiante->name,
            'DNI' => $estudiante === null ? null : $estudiante->dni,
            'Código' => $perfil === null ? null : $perfil->codigo_estudiante,
            'Ciclo' => $perfil === null ? null : $perfil->ciclo_actual,
            'Año de egreso' => $perfil === null ? null : $perfil->anio_egreso,
        ] as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $academicos[] = $etiqueta.': '.$valor;
            }
        }

        return [
            'NUMERO_DOCUMENTO_PREVIO' => 'BORRADOR SIN NUMERACIÓN OFICIAL',
            'ASUNTO' => trim(strip_tags((string) $datos['asunto'])),
            'LUGAR' => $lugar,
            'FECHA' => $fecha,
            'LUGAR_FECHA' => $this->fechaDocumentoEspanol($fecha, $lugar),
            'PROGRAMA_ESTUDIO' => (string) ($programa ?? ''),
            'ESTUDIANTE_NOMBRE' => (string) ($estudiante === null ? '' : $estudiante->name),
            'DNI' => (string) ($estudiante === null ? '' : $estudiante->dni),
            'CODIGO_ESTUDIANTE' => (string) ($perfil === null ? '' : $perfil->codigo_estudiante),
            'CICLO' => (string) ($perfil === null ? '' : $perfil->ciclo_actual),
            'ANIO_EGRESO' => (string) ($perfil === null ? '' : $perfil->anio_egreso),
            'DESTINATARIO_NOMBRE' => $destinatario === null ? '' : $nombre($destinatario),
            'DESTINATARIO_CARGO' => (string) ($destinatario['cargo'] ?? ''),
            'LISTA_DESTINATARIOS' => implode('; ', array_map($nombreConCargo, $destinatarios)),
            'LISTA_PERSONAS_MENCIONADAS' => implode('; ', array_map($nombreConCargo, $personas)),
            'DOCUMENTOS_ADJUNTOS' => implode('; ', array_map(static fn (array $adjunto): string => $adjunto['nombre']
                .' — '.str_replace('_', ' ', $adjunto['categoria']).' · versión '.$adjunto['version'], $adjuntos)),
            'DATOS_ESTUDIANTE_OPCIONALES' => implode('; ', $academicos),
            'REMITENTE_NOMBRE' => (string) ($remitente->name ?? ''),
            'REMITENTE_CARGO' => (string) ($remitente->cargo ?? ''),
            'FIRMANTE_NOMBRE' => (string) ($firmante->name ?? ''),
            'FIRMANTE_CARGO' => (string) ($firmante->cargo ?? ''),
            'INTRODUCCION' => $this->textoOpcional($datos['introduccion'] ?? null) ?? '',
            'CONTENIDO_PRINCIPAL' => $this->textoOpcional($datos['contenido_principal'] ?? null) ?? '',
            'CIERRE' => $this->textoOpcional($datos['cierre'] ?? null) ?? '',
        ];
    }

    private function fechaDocumentoEspanol(string $fecha, string $lugar): string
    {
        $partes = explode('-', $fecha);
        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $mes = $meses[(int) ($partes[1] ?? 0)] ?? '';

        return $lugar.', '.(int) ($partes[2] ?? 0).' de '.$mes.' de '.$partes[0];
    }

    /**
     * @param  Collection<int, TramiteObservacionRevision>  $observaciones
     * @return array<int, array{observacion_id: int, respuesta: string}>
     */
    private function validarRespuestas(Collection $observaciones, mixed $respuestas): array
    {
        if (! is_array($respuestas)) {
            throw ValidationException::withMessages(['respuestas' => 'Las respuestas no tienen un formato válido.']);
        }

        $ids = $observaciones->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        foreach (array_keys($respuestas) as $id) {
            if (! in_array((string) $id, $ids, true)) {
                throw ValidationException::withMessages(['respuestas' => 'Una respuesta no corresponde a una observación de esta ronda.']);
            }
        }

        $validas = [];

        foreach ($observaciones as $observacion) {
            $respuesta = trim(strip_tags((string) ($respuestas[$observacion->id] ?? '')));

            if ($observacion->obligatoria && mb_strlen($respuesta) < 5) {
                throw ValidationException::withMessages(["respuestas.{$observacion->id}" => 'Responde esta observación obligatoria con al menos 5 caracteres.']);
            }

            if ($respuesta !== '' && mb_strlen($respuesta) < 5) {
                throw ValidationException::withMessages(["respuestas.{$observacion->id}" => 'La respuesta debe tener al menos 5 caracteres.']);
            }

            if ($respuesta !== '') {
                $validas[] = ['observacion_id' => $observacion->id, 'respuesta' => $respuesta];
            }
        }

        return $validas;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>  $destinatarios
     */
    private function validarBorrador(Tramite $tramite, TramitePlantilla $plantilla, array $datos, array $destinatarios, bool $preparar): void
    {
        if (! $preparar) {
            return;
        }

        $errores = [];
        $remitenteValido = User::query()
            ->whereKey($datos['remitente_id'] ?? 0)
            ->where('activo', true)
            ->where('estado_cuenta', 'activo')
            ->whereIn('rol', ['docente', 'administrador'])
            ->whereNotNull('cargo_institucional_id')
            ->exists();
        $firmanteValido = User::query()
            ->whereKey($datos['firmante_id'] ?? 0)
            ->where('activo', true)
            ->where('estado_cuenta', 'activo')
            ->whereIn('rol', ['docente', 'administrador'])
            ->whereNotNull('cargo_institucional_id')
            ->exists();

        if (! $remitenteValido) {
            $errores['remitente_id'] = 'Seleccione un remitente activo con cargo institucional.';
        }

        if (! $firmanteValido) {
            $errores['firmante_id'] = 'Seleccione un firmante activo con cargo institucional.';
        }

        if (mb_strlen(trim((string) ($datos['contenido_principal'] ?? ''))) < 20) {
            $errores['contenido_principal'] = 'El contenido principal debe tener al menos 20 caracteres.';
        }

        $principales = count(array_filter($destinatarios, fn (array $destinatario): bool => $destinatario['principal']));

        if ($plantilla->modalidad === 'multiple') {
            if (count($destinatarios) < 2 || $principales < 1) {
                $errores['destinatarios'] = 'El memorando múltiple exige dos destinatarios y uno principal.';
            }
        } elseif (count($destinatarios) !== 1 || $principales !== 1) {
            $errores['destinatarios'] = 'La plantilla exige exactamente un destinatario principal.';
        }

        if ($tramite->fecha_recepcion !== null
            && $datos['fecha_documento'] < $tramite->fecha_recepcion->toDateString()
            && ! (bool) ($datos['confirmar_fecha_anterior'] ?? false)) {
            $errores['confirmar_fecha_anterior'] = 'Confirme expresamente la fecha anterior a la recepción.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * @return array<int, array{nombres: string, apellidos: ?string, cargo: ?string, correo: ?string, principal: bool}>
     */
    private function destinatarios(mixed $destinatarios): array
    {
        if (! is_array($destinatarios)) {
            return [];
        }

        return collect($destinatarios)->map(fn (array $destinatario): array => [
            'nombres' => trim(strip_tags((string) ($destinatario['nombres'] ?? ''))),
            'apellidos' => $this->textoOpcional($destinatario['apellidos'] ?? null),
            'cargo' => $this->textoOpcional($destinatario['cargo'] ?? null),
            'correo' => $this->textoOpcional($destinatario['correo'] ?? null),
            'principal' => (bool) ($destinatario['principal'] ?? false),
        ])->filter(fn (array $destinatario): bool => $destinatario['nombres'] !== '')->values()->all();
    }

    /**
     * @return array<int, array{nombres: string, apellidos: ?string, cargo: ?string}>
     */
    private function personasMencionadas(mixed $personas): array
    {
        if (! is_array($personas)) {
            return [];
        }

        return collect($personas)->map(fn (array $persona): array => [
            'nombres' => trim(strip_tags((string) ($persona['nombres'] ?? ''))),
            'apellidos' => $this->textoOpcional($persona['apellidos'] ?? null),
            'cargo' => $this->textoOpcional($persona['cargo'] ?? null),
        ])->filter(fn (array $persona): bool => $persona['nombres'] !== '')->values()->all();
    }

    /**
     * @return array<int, array{id: int, nombre: string, categoria: string, version: int}>
     */
    private function adjuntos(Tramite $tramite, mixed $ids): array
    {
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $documentos = $tramite->documentos()
            ->where('vigente', true)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'nombre_original', 'categoria', 'version']);

        if ($documentos->count() !== count($ids)) {
            throw ValidationException::withMessages(['adjuntos' => 'Un archivo seleccionado no pertenece a este trámite.']);
        }

        return $documentos->map(fn ($documento): array => [
            'id' => $documento->id,
            'nombre' => $documento->nombre_original,
            'categoria' => $documento->categoria,
            'version' => $documento->version,
        ])->all();
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim(strip_tags((string) ($valor ?? '')));

        return $texto === '' ? null : $texto;
    }
}
