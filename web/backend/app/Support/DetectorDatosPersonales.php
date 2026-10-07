<?php

namespace App\Support;

/**
 * Detección de identificadores directos por patrones (paso 2 del protocolo).
 *
 * Es solo una ayuda para la revisión humana: no detecta nombres propios ni
 * identificadores indirectos (lugares, fechas, hechos poco frecuentes).
 */
class DetectorDatosPersonales
{
    public const MARCADOR = '[dato personal eliminado]';

    /**
     * El orden importa al sustituir: primero los patrones más largos, para que
     * un IBAN no se quede a medias porque antes se ha tapado un "teléfono".
     *
     * @var array<string, array{etiqueta: string, patron: string}>
     */
    private const PATRONES = [
        'iban' => [
            'etiqueta' => 'IBAN',
            'patron' => '/\bES\d{2}(?:[\s-]?\d{4}){5}\b/iu',
        ],
        'email' => [
            'etiqueta' => 'Correo electrónico',
            'patron' => '/[\p{L}0-9._%+-]+@[\p{L}0-9.-]+\.[\p{L}]{2,}/iu',
        ],
        'nie' => [
            'etiqueta' => 'NIE',
            'patron' => '/\b[XYZ][\s-]?\d{7}[\s-]?[A-HJ-NP-TV-Z]\b/iu',
        ],
        'dni' => [
            'etiqueta' => 'DNI',
            'patron' => '/\b\d{8}[\s-]?[A-HJ-NP-TV-Z]\b/iu',
        ],
        'direccion' => [
            'etiqueta' => 'Dirección',
            'patron' => '/\b(?:c\/|calle|avda\.?|avenida|plaza|pza\.?|paseo|barriada|bda\.?)\s*[\p{L}][\p{L}\s.\']{1,40}?,?\s*(?:n[º°o]\.?\s*)?\d{1,4}\b/iu',
        ],
        'telefono' => [
            'etiqueta' => 'Teléfono',
            'patron' => '/(?<![\d\p{L}])(?:\+?34[\s.-]?)?[6-9](?:[\s.-]?\d){8}(?!\d)/u',
        ],
    ];

    /**
     * @return list<array{tipo: string, etiqueta: string, valor: string, inicio: int, fin: int}>
     */
    public static function detectar(?string $texto): array
    {
        if (blank($texto)) {
            return [];
        }

        $coincidencias = [];

        foreach (self::PATRONES as $tipo => $definicion) {
            preg_match_all($definicion['patron'], $texto, $encontrados, PREG_OFFSET_CAPTURE);

            foreach ($encontrados[0] as [$valor, $inicio]) {
                $coincidencias[] = [
                    'tipo' => $tipo,
                    'etiqueta' => $definicion['etiqueta'],
                    'valor' => $valor,
                    'inicio' => $inicio,
                    'fin' => $inicio + strlen($valor),
                ];
            }
        }

        usort($coincidencias, fn (array $a, array $b): int => [$a['inicio'], $b['fin'] - $b['inicio']]
            <=> [$b['inicio'], $a['fin'] - $a['inicio']]);

        $sinSolapes = [];
        $finAnterior = -1;

        foreach ($coincidencias as $coincidencia) {
            if ($coincidencia['inicio'] >= $finAnterior) {
                $sinSolapes[] = $coincidencia;
                $finAnterior = $coincidencia['fin'];
            }
        }

        return $sinSolapes;
    }

    /**
     * Sustituye cada identificador detectado por el marcador del protocolo.
     */
    public static function sustituir(?string $texto): string
    {
        $texto ??= '';

        foreach (self::PATRONES as $definicion) {
            $texto = preg_replace($definicion['patron'], self::MARCADOR, $texto);
        }

        return $texto;
    }

    /**
     * Devuelve el texto escapado para HTML con los hallazgos envueltos en <mark>.
     */
    public static function resaltar(?string $texto): string
    {
        $texto ??= '';
        $html = '';
        $cursor = 0;

        foreach (self::detectar($texto) as $coincidencia) {
            $html .= e(substr($texto, $cursor, $coincidencia['inicio'] - $cursor));
            $html .= '<mark class="bg-red-100 text-red-800 rounded px-0.5" title="'.e($coincidencia['etiqueta']).'">'
                .e($coincidencia['valor']).'</mark>';
            $cursor = $coincidencia['fin'];
        }

        return $html.e(substr($texto, $cursor));
    }
}
