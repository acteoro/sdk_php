<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Acteo\Exception\EroareConfigurare;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** @internal Valideaza optiunile constructorului si construieste setarile clientului. */
final class Configurare
{
    private function __construct() {}

    /**
     * @param array{incercari?: int, intarziereInitialaMs?: int, intarziereMaximaMs?: int}|false $retry
     *
     * @throws EroareConfigurare
     */
    public static function setari(
        ProfilClient $profil,
        string $versiune,
        ?string $apiKey,
        string $baseUrl,
        int $timeoutMs,
        array|false $retry,
        ?ClientInterface $httpClient,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
        ?string $userAgent,
        ?Ceas $ceas,
    ): Setari {
        if ($timeoutMs <= 0) {
            throw new EroareConfigurare('`timeoutMs` trebuie sa fie pozitiv.');
        }
        $userAgent = trim($userAgent ?? '');

        return new Setari(
            apiKey: self::cheie($apiKey, $profil),
            baseUrl: self::adresa($baseUrl),
            retry: $retry === false ? null : SetariRetry::dinOptiuni($retry),
            http: FabricaHttp::client($httpClient, $timeoutMs),
            cereri: FabricaHttp::fabricaCereri($requestFactory),
            fluxuri: FabricaHttp::fabricaFluxuri($streamFactory),
            userAgent: $profil->agent . '/' . $versiune . ($userAgent !== '' ? ' ' . $userAgent : ''),
            ceas: $ceas ?? new CeasSistem(),
            urlImplicit: $profil->urlImplicit,
            timeoutMs: $timeoutMs,
            clientCuTimeout: $httpClient === null ? static fn(int $ms): ClientInterface => FabricaHttp::client(null, $ms) : null,
        );
    }

    private static function cheie(?string $apiKey, ProfilClient $profil): string
    {
        $cheie = trim($apiKey ?? self::cheieDinMediu($profil) ?? '');
        if ($cheie === '') {
            throw new EroareConfigurare(
                'Lipseste cheia API. Trimite `apiKey` sau seteaza variabila de mediu ' . $profil->variabileCheie[0] . '.',
                hint: 'Cheia se genereaza din ' . $profil->undeCheie,
            );
        }
        if (strlen($cheie) < 16 || strlen($cheie) > 128) {
            throw new EroareConfigurare('Cheia API are un format invalid (intre 16 si 128 de caractere).');
        }

        return $cheie;
    }

    /** Prima variabila de mediu din profil care are o valoare. */
    private static function cheieDinMediu(ProfilClient $profil): ?string
    {
        foreach ($profil->variabileCheie as $nume) {
            $valoare = self::citesteMediu($nume);
            if ($valoare !== null && trim($valoare) !== '') {
                return $valoare;
            }
        }

        return null;
    }

    private static function adresa(string $baseUrl): string
    {
        $schema = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || ($schema !== 'http' && $schema !== 'https')) {
            throw new EroareConfigurare("`baseUrl` invalid: {$baseUrl}");
        }

        return rtrim($baseUrl, '/');
    }

    private static function citesteMediu(string $nume): ?string
    {
        $valoare = getenv($nume);
        if (is_string($valoare) && $valoare !== '') {
            return $valoare;
        }
        $valoare = $_ENV[$nume] ?? $_SERVER[$nume] ?? null;

        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }
}
