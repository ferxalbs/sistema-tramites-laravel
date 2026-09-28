<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideTramiteRevisionRequest;
use App\Http\Requests\StoreTramiteRevisionObservationRequest;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Tramites\ManageTramiteReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TramiteRevisionController extends Controller
{
    public function start(Request $request, Tramite $tramite, ManageTramiteReview $manageTramiteReview): RedirectResponse
    {
        $manageTramiteReview->start($tramite, $request->user());

        return redirect()->to($this->reviewerShowUrl($tramite, $request->user()))
            ->with('success', 'La revisión quedó iniciada sobre la versión preparada.');
    }

    public function observe(StoreTramiteRevisionObservationRequest $request, Tramite $tramite, ManageTramiteReview $manageTramiteReview): RedirectResponse
    {
        $manageTramiteReview->observe($tramite, $request->validated(), $request->user());

        return redirect()->to($this->reviewerShowUrl($tramite, $request->user()))
            ->with('success', 'Las observaciones quedaron registradas.');
    }

    public function decide(DecideTramiteRevisionRequest $request, Tramite $tramite, ManageTramiteReview $manageTramiteReview): RedirectResponse
    {
        $manageTramiteReview->decide($tramite, $request->validated(), $request->user());

        return redirect()->route($request->user()->rol === 'docente' ? 'asignaciones.docente.index' : 'asignaciones.oficina.index')
            ->with('success', 'La decisión quedó registrada y finalizó la asignación.');
    }

    private function reviewerShowUrl(Tramite $tramite, User $user): string
    {
        $route = $user->rol === 'docente' ? 'asignaciones.docente.show' : 'asignaciones.oficina.show';

        return route($route, $tramite);
    }
}
