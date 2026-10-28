<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Acteo\Exception\ActeoException;
use Acteo\Exception\EroareAutentificare;
use Acteo\Exception\EroareConflict;
use Acteo\Exception\EroareHttp;
use Acteo\Exception\EroareInterzis;
use Acteo\Exception\EroareLimita;
use Acteo\Exception\EroareNegasit;
use Acteo\Exception\EroarePlanInactiv;
use Acteo\Exception\EroareServer;
use Acteo\Exception\EroareValidare;

/** @internal Transforma un raspuns non-2xx in eroarea tipata potrivita. */
final class Erori
{
    private function __construct() {}

    /**
     * @param array<string, string> $headere chei lowercase
     * @param string|null $urlImplicit adresa implicita a clientului, aratata in hint la redirect
     */
    public static function dinRaspuns(int $status, array $headere, mixed $corp, int $acumMs, ?string $urlImplicit = null): ActeoException
    {
        $c = is_array($corp) && is_string($corp['error'] ?? null) ? $corp : [];
        $sir = static fn(string $cheie): ?string => isset($c[$cheie]) && is_string($c[$cheie]) ? $c[$cheie] : null;

        $mesaj = $sir('error') ?? "Cererea a esuat cu HTTP {$status}.";
        $hint = $sir('hint');
        $cod = $sir('code');

        switch ($status) {
            case 400:
                $acceptate = isset($c['accepted']) && is_array($c['accepted'])
                    ? array_values(array_filter($c['accepted'], 'is_string'))
                    : null;

                return new EroareValidare($mesaj, $status, $cod, $hint, $corp, acceptate: $acceptate);
            case 401:
                return new EroareAutentificare($mesaj, $status, $cod, $hint, $corp);
            case 402:
                return new EroarePlanInactiv($mesaj, $status, $cod, $hint, $corp);
            case 403:
                $limita = $c['limita'] ?? null;

                return new EroareInterzis($mesaj, $status, $cod, $hint, $corp, limita: is_int($limita) ? $limita : (is_float($limita) ? (int) $limita : null));
            case 404:
                return new EroareNegasit($mesaj, $status, $cod, $hint, $corp);
            case 409:
                return new EroareConflict($mesaj, $status, $cod, $hint, $corp);
            case 429:
                $zi = $sir('dailyUsage');
                $luna = $sir('monthlyUsage');
                $cod ??= (($zi ?? '') !== '' || ($luna ?? '') !== '') ? 'COTA_ATINSA' : 'PREA_MULTE_CERERI';

                return new EroareLimita(
                    $mesaj,
                    $status,
                    $cod,
                    $hint,
                    $corp,
                    retryAfterMs: self::retryAfterMs($headere['retry-after'] ?? null, $acumMs),
                    utilizareZi: $zi,
                    utilizareLuna: $luna,
                );
        }

        if ($status >= 300 && $status < 400) {
            $locatie = $headere['location'] ?? '';

            return new EroareHttp(
                $locatie !== '' ? "API-ul a raspuns cu redirect catre {$locatie}." : "API-ul a raspuns cu redirect (HTTP {$status}).",
                $status,
                $cod,
                'Foloseste adresa canonica in `baseUrl`' . ($urlImplicit !== null ? ': ' . $urlImplicit : '.'),
                $corp,
            );
        }

        return $status >= 500
            ? new EroareServer($mesaj, $status, $cod, $hint, $corp)
            : new EroareHttp($mesaj, $status, $cod, $hint, $corp);
    }

    /** `Retry-After` vine in secunde sau ca data HTTP. */
    public static function retryAfterMs(?string $valoare, int $acumMs): ?int
    {
        $valoare = trim($valoare ?? '');
        if ($valoare === '') {
            return null;
        }
        if (is_numeric($valoare)) {
            return max(0, (int) round((float) $valoare * 1000));
        }
        $data = strtotime($valoare);

        return $data === false ? null : max(0, $data * 1000 - $acumMs);
    }
}
