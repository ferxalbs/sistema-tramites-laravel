<?php

namespace App\Services\Tramites;

use App\Models\Tramite;
use App\Models\TramitePlantilla;

final class TramiteTemplateEligibility
{
    private const REFERENTIAL_TEMPLATES = [
        'JUSTIFICACION_TARDANZA' => 'JUSTIFICACION_TARDANZA_REFERENCIAL',
        'CONSTANCIA_PRACTICA' => 'CONSTANCIA_PRACTICA_REFERENCIAL',
    ];

    public static function isReferentialTemplate(TramitePlantilla $plantilla): bool
    {
        return in_array($plantilla->codigo, self::REFERENTIAL_TEMPLATES, true);
    }

    public static function availableForDraft(Tramite $tramite, TramitePlantilla $plantilla): bool
    {
        if (! $plantilla->activa || ! self::allows($tramite, $plantilla)) {
            return false;
        }

        if (isset(self::REFERENTIAL_TEMPLATES[$tramite->tipo_documento])) {
            return $plantilla->estado === 'borrador';
        }

        return $plantilla->estado === 'publicada';
    }

    public static function allows(Tramite $tramite, TramitePlantilla $plantilla): bool
    {
        if (isset(self::REFERENTIAL_TEMPLATES[$tramite->tipo_documento])) {
            return $plantilla->codigo === self::REFERENTIAL_TEMPLATES[$tramite->tipo_documento];
        }

        if (self::isReferentialTemplate($plantilla)) {
            return false;
        }

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
