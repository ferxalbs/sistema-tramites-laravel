<?php

namespace App\Http\Requests;

use App\Rules\SafeReceptionDocument;
use App\Services\Tramites\TramiteTypeCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTramiteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'formato_salida' => $this->input('formato_salida') === 'pendiente' ? null : $this->input('formato_salida'),
            'modalidad_documento' => $this->input('modalidad_documento'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clasificacion' => ['required', 'string', Rule::exists('clasificaciones_expediente', 'codigo')->where(fn (Builder $query) => $query
                ->where('activo', true)->where('codigo', '<>', 'institucional'))],
            'tipo_documento' => [
                'required',
                'string',
                Rule::exists('tipos_tramite', 'codigo')->where(fn (Builder $query) => $query
                    ->where('activo', true)
                    ->whereNotIn('codigo', TramiteTypeCatalog::excludedCodes())
                    ->where(fn (Builder $classification) => $classification
                        ->whereNull('clasificacion_sugerida')
                        ->orWhere('clasificacion_sugerida', $this->input('clasificacion'))
                        ->when($this->input('clasificacion') === 'administrativo', fn (Builder $query) => $query
                            ->orWhere('clasificacion_sugerida', 'institucional')))),
            ],
            'formato_salida' => ['nullable', 'string', Rule::exists('tipos_documento_salida', 'codigo')->where('activo', true)],
            'modalidad_documento' => ['nullable', 'string', Rule::exists('modalidades_documento', 'codigo')->where('activo', true)],
            'persona_nombre' => ['required', 'string', 'max:200'],
            'persona_identificador' => ['nullable', 'string', 'max:50'],
            'propietario_id' => [
                'nullable',
                'required_if:clasificacion,estudiantil',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('rol', 'estudiante')->where('activo', true)),
            ],
            'programa_estudio_id' => [
                'nullable',
                'integer',
                Rule::exists('programas_estudio', 'id')->where(fn (Builder $query) => $query->where('activo', true)),
            ],
            'destino_tipo' => ['required', 'string', Rule::in(array_keys(config('tramites.destinos')))],
            'destino_nombre' => ['nullable', 'string', 'max:200'],
            'destino_docente_id' => [
                'required_if:destino_tipo,docente',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query
                    ->where('rol', 'docente')
                    ->where('activo', true)
                    ->where('estado_cuenta', 'activo')),
            ],
            'asunto' => ['required', 'string', 'min:3', 'max:255'],
            'descripcion' => ['required', 'string', 'min:3', 'max:5000'],
            'prioridad' => ['required', 'string', Rule::in(array_keys(config('tramites.prioridades')))],
            'fecha_llegada_oficina' => ['required', 'date_format:Y-m-d\\TH:i'],
            'fecha_presentacion_original' => ['nullable', 'date_format:Y-m-d'],
            'numero_expediente_externo' => ['nullable', 'string', 'max:80', 'regex:/^[\\p{L}\\p{N}\\s.\\-\\/#]+$/u'],
            'area_procedencia' => ['nullable', 'string', 'max:160'],
            'persona_entrega_documento' => ['nullable', 'string', 'max:180'],
            'observacion_recepcion' => ['nullable', 'string', 'max:2000'],
            'folios' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'confirmar_recepcion' => ['required', 'accepted'],
            'documentos' => ['sometimes', 'array'],
            'documentos.*' => ['required', 'array:categoria,archivo'],
            'documentos.*.categoria' => ['required', 'string', Rule::in(['documento_original', 'documento_escaneado'])],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240', new SafeReceptionDocument],
            'personas_relacionadas' => ['sometimes', 'array'],
            'personas_relacionadas.*' => ['required', 'array:nombres,apellidos,dni,cargo_funcion,tipo_relacion'],
            'personas_relacionadas.*.nombres' => ['required', 'string', 'min:2', 'max:120'],
            'personas_relacionadas.*.apellidos' => ['nullable', 'string', 'max:120'],
            'personas_relacionadas.*.dni' => ['nullable', 'regex:/^[0-9]{8}$/'],
            'personas_relacionadas.*.cargo_funcion' => ['nullable', 'string', 'max:160'],
            'personas_relacionadas.*.tipo_relacion' => ['required', Rule::in([
                'interesado', 'solicitante', 'personal_autorizado', 'participante',
                'personal_externo', 'persona_mencionada', 'otro',
            ])],
            'destinatarios' => ['sometimes', 'array'],
            'destinatarios.*' => ['required', 'array:nombres,apellidos,cargo_institucional_id,cargo_texto,correo_institucional'],
            'destinatarios.*.nombres' => ['required', 'string', 'min:2', 'max:120'],
            'destinatarios.*.apellidos' => ['nullable', 'string', 'max:120'],
            'destinatarios.*.cargo_institucional_id' => ['nullable', 'integer', Rule::exists('cargos_institucionales', 'id')->where('activo', true)],
            'destinatarios.*.cargo_texto' => ['nullable', 'string', 'max:160'],
            'destinatarios.*.correo_institucional' => ['nullable', 'email:rfc', 'max:190'],
            'personas_mencionadas' => ['sometimes', 'array'],
            'personas_mencionadas.*' => ['required', 'array:nombres,apellidos,dni,cargo_funcion,descripcion'],
            'personas_mencionadas.*.nombres' => ['required', 'string', 'min:2', 'max:120'],
            'personas_mencionadas.*.apellidos' => ['nullable', 'string', 'max:120'],
            'personas_mencionadas.*.dni' => ['nullable', 'regex:/^[0-9]{8}$/'],
            'personas_mencionadas.*.cargo_funcion' => ['nullable', 'string', 'max:160'],
            'personas_mencionadas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['tipo_documento', 'formato_salida', 'modalidad_documento'])) {
                return;
            }

            $formato = $this->input('formato_salida');
            $modalidad = $this->input('modalidad_documento');
            $sugerido = DB::table('tipos_tramite')->where('codigo', $this->input('tipo_documento'))
                ->value('tipo_documento_salida_sugerido');

            if ($sugerido !== null && $formato !== $sugerido) {
                $validator->errors()->add('formato_salida', 'El formato documental no es compatible con el tipo de trámite.');
            }

            if ($formato === 'memorando') {
                if (! is_string($modalidad) || ! DB::table('modalidades_documento')
                    ->where('tipo_documento_salida', 'memorando')
                    ->where('codigo', $modalidad)
                    ->where('activo', true)
                    ->exists()) {
                    $validator->errors()->add('modalidad_documento', 'El Memorando exige una modalidad simple o múltiple válida.');
                }
            } elseif ($modalidad !== null) {
                $validator->errors()->add('modalidad_documento', 'Este formato no admite modalidad de Memorando.');
            }

            $tipo = DB::table('tipos_tramite')->where('codigo', $this->input('tipo_documento'))->first([
                'requiere_personas_relacionadas', 'requiere_destinatarios_multiples', 'requiere_documento_original',
            ]);
            if ($tipo?->requiere_personas_relacionadas && count((array) $this->input('personas_relacionadas', [])) < 1) {
                $validator->errors()->add('personas_relacionadas', 'Este tipo de trámite exige al menos una persona relacionada.');
            }
            if ($tipo?->requiere_destinatarios_multiples && count((array) $this->input('destinatarios', [])) < 2) {
                $validator->errors()->add('destinatarios', 'Este tipo de trámite exige al menos dos destinatarios.');
            }
            if ($tipo?->requiere_documento_original && ! $this instanceof UpdateTramiteRequest
                && ! collect((array) $this->input('documentos', []))->contains(fn (mixed $documento): bool => is_array($documento) && ($documento['categoria'] ?? null) === 'documento_original')) {
                $validator->errors()->add('documentos', 'Este tipo de trámite exige un documento original digitalizado.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clasificacion' => 'clasificación',
            'tipo_documento' => 'tipo de documento',
            'formato_salida' => 'formato documental previsto',
            'modalidad_documento' => 'modalidad del Memorando',
            'persona_nombre' => 'nombre de la persona solicitante',
            'persona_identificador' => 'DNI o documento de identidad',
            'propietario_id' => 'estudiante o egresado relacionado',
            'programa_estudio_id' => 'programa de estudios',
            'destino_tipo' => 'tipo de destino',
            'destino_nombre' => 'destino',
            'destino_docente_id' => 'docente de destino',
            'asunto' => 'resumen de la solicitud (sumilla)',
            'descripcion' => 'fundamentación del pedido',
            'prioridad' => 'prioridad',
            'fecha_llegada_oficina' => 'fecha y hora de recepción en Mesa de Partes',
            'fecha_presentacion_original' => 'fecha del documento (FUT)',
            'numero_expediente_externo' => 'referencia física externa',
            'area_procedencia' => 'área de procedencia',
            'persona_entrega_documento' => 'persona que entrega el documento',
            'observacion_recepcion' => 'observación de recepción',
            'folios' => 'número de folios',
            'confirmar_recepcion' => 'confirmación de la recepción física',
            'documentos.*.categoria' => 'categoría del documento',
            'documentos.*.archivo' => 'archivo recibido',
            'personas_relacionadas' => 'personas relacionadas',
            'destinatarios' => 'destinatarios preliminares',
            'personas_mencionadas' => 'personas mencionadas',
        ];
    }
}
