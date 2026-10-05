<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramitePlantilla;

final class TramiteTemplateEligibility
{
    public static function allows(Tramite $tramite, TramitePlantilla $plantilla): bool
    {
        $isTitlingRequest = $tramite->tipo_documento === 'CONSTANCIA_MODALIDAD_TITULACION';
        $isTitlingTemplate = $plantilla->codigo === 'CONSTANCIA_MODALIDAD_TITULACION';

        if ($isTitlingRequest || $isTitlingTemplate) {
            return $isTitlingRequest && $isTitlingTemplate;
        }

        return match ($tramite->tipo_documento) {
            'INFORME' => $plantilla->tipo_documento_salida === 'informe',
            'MEMORANDO_SIMPLE' => $plantilla->tipo_documento_salida === 'memorando'
                && $plantilla->modalidad === 'simple',
            'MEMORANDO_MULTIPLE' => $plantilla->tipo_documento_salida === 'memorando'
                && $plantilla->modalidad === 'multiple',
            default => true,
        };
    }
}
