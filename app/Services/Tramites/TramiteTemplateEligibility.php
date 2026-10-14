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

        if (self::isReferentialTemplate($plantilla)) {
            return $plantilla->estado === 'borrador';
        }

        if ($plantilla->modelo_propuesto && $plantilla->validada_en === null && ! self::demoEnabled()) {
            return false;
        }

        return $plantilla->estado === 'publicada';
    }

    public static function allows(Tramite $tramite, TramitePlantilla $plantilla): bool
    {
        if ($plantilla->tipo_tramite !== null && $plantilla->tipo_tramite !== $tramite->tipo_documento) {
            return false;
        }
        if (isset(self::REFERENTIAL_TEMPLATES[$tramite->tipo_documento])) {
            return $plantilla->tipo_tramite === $tramite->tipo_documento
                || $plantilla->codigo === self::REFERENTIAL_TEMPLATES[$tramite->tipo_documento];
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

    public static function demoEnabled(): bool
    {
        return app()->environment('local', 'testing') && (bool) config('tramites.modelos_prueba');
    }

    public static function isDemo(TramitePlantilla $plantilla): bool
    {
        return $plantilla->modelo_propuesto && $plantilla->validada_en === null;
    }

    public static function canIssue(Tramite $tramite, TramitePlantilla $plantilla): bool
    {
        if (! self::allows($tramite, $plantilla) || self::isReferentialTemplate($plantilla)) {
            return false;
        }
        if (self::isDemo($plantilla)) {
            return self::demoEnabled();
        }
        if (in_array($tramite->tipo_documento, config('tramites.modelos_oficiales_pendientes', []), true)) {
            return $plantilla->validada_en !== null;
        }

        return true;
    }
}
