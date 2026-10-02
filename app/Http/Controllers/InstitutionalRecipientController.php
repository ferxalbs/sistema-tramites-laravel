<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InstitutionalRecipientController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('destinatarios-institucionales', [
            'destinatarios' => DB::table('destinatarios_institucionales')
                ->orderByDesc('activo')->orderBy('apellidos')->orderBy('nombres')
                ->get(['id', 'codigo', 'nombres', 'apellidos', 'cargo', 'correo', 'activo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($request, $data): void {
            $id = DB::table('destinatarios_institucionales')->insertGetId([
                ...$data,
                'codigo' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit($request, $id, null, $data);
        });

        return to_route('admin.recipients.index')->with('success', 'Destinatario agregado.');
    }

    public function update(Request $request, int $recipient): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($request, $recipient, $data): void {
            $before = DB::table('destinatarios_institucionales')->where('id', $recipient)
                ->first(['nombres', 'apellidos', 'cargo', 'correo', 'activo', 'updated_at']);
            abort_if($before === null, 404);

            $changed = DB::table('destinatarios_institucionales')->where('id', $recipient)
                ->where('updated_at', $before->updated_at)
                ->update([...$data, 'updated_at' => now()]);
            abort_unless($changed === 1, 409);

            $this->audit($request, $recipient, collect((array) $before)->except('updated_at')->all(), $data);
        });

        return to_route('admin.recipients.index')->with('success', 'Destinatario actualizado.');
    }

    private function validateData(Request $request): array
    {
        $request->merge([
            'nombres' => trim((string) $request->input('nombres', '')),
            'apellidos' => trim((string) $request->input('apellidos', '')),
            'cargo' => trim((string) $request->input('cargo', '')),
            'correo' => trim((string) $request->input('correo', '')) ?: null,
        ]);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:120'],
            'apellidos' => ['required', 'string', 'min:2', 'max:120'],
            'cargo' => ['required', 'string', 'min:2', 'max:180'],
            'correo' => ['nullable', 'email', 'max:255'],
            'activo' => ['required', 'boolean', Rule::in([0, 1, '0', '1'])],
        ]);

        return [...$data, 'activo' => (bool) $data['activo']];
    }

    private function audit(Request $request, int $id, ?array $before, array $after): void
    {
        DB::table('tramite_config_events')->insert([
            'actor_id' => $request->user()->id,
            'accion' => $before === null ? 'alta_catalogo_documental' : 'edicion_catalogo_documental',
            'entidad' => 'destinatario_institucional',
            'entidad_id' => $id,
            'valor_anterior' => json_encode($before, JSON_THROW_ON_ERROR),
            'valor_nuevo' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
