<?php

declare(strict_types=1);

namespace VerificRca;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use VerificRca\Exception\EroareConfigurare;
use VerificRca\Internal\Ceas;
use VerificRca\Internal\CeasSistem;
use VerificRca\Internal\FabricaHttp;
use VerificRca\Internal\Http;
use VerificRca\Internal\Setari;
use VerificRca\Internal\SetariRetry;

/**
 * Clientul API verificrca.ro: verificare RCA, ITP, rovinieta si ARR, plus
 * gestionarea vehiculelor monitorizate.
 *
 * Toate metodele arunca subclase de `VerificRcaException`. Erorile trecatoare
 * (retea, 5xx, throttle la polling) sunt reincercate automat, fara sa repete
 * vreodata o verificare taxata.
 *
 * Exemplu:
 *     $vrca = new VerificRca(apiKey: getenv('VERIFICRCA_API_KEY') ?: null);
 *     $rca = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Rca, 'B123ABC'));
 *     echo $rca->status->value, ' ', $rca->expiresAt?->format('Y-m-d');
 */
final class VerificRca
{
    /** Versiunea acestui SDK, trimisa si in headerul `User-Agent`. */
    public const VERSIUNE_SDK = '0.2.0';

    /** Adresa implicita a API-ului. */
    public const URL_IMPLICIT = 'https://www.verificrca.ro';

    /** Verificari la cerere RCA, ITP, rovinieta si ARR. */
    public readonly ClientVerificari $verificari;

    /** Vehiculele monitorizate din cont. */
    public readonly ClientVehicule $vehicule;

    /**
     * Creeaza un client. Nu face nicio cerere pe retea.
     *
     * @param string|null $apiKey cheia API (`vrca_...`) din https://www.verificrca.ro/dashboard/api;
     *   implicit din variabila de mediu `VERIFICRCA_API_KEY`
     * @param string $baseUrl adresa API-ului; schimb-o doar pentru medii de test
     * @param int $timeoutMs timeout per cerere HTTP; se aplica doar clientului construit de SDK
     *   (Guzzle sau Symfony HttpClient). Un `$httpClient` injectat isi pastreaza propriul timeout.
     * @param array{incercari?: int, intarziereInitialaMs?: int, intarziereMaximaMs?: int}|false $retry
     *   reincercari pentru erori trecatoare, cu backoff exponential si jitter; `false` le dezactiveaza.
     *   Implicit: 3 reincercari, de la 500 ms, maxim 10 s pe pauza.
     * @param ClientInterface|null $httpClient client PSR-18 propriu (proxy, teste)
     * @param RequestFactoryInterface|null $requestFactory fabrica PSR-17; implicit descoperita automat
     * @param StreamFactoryInterface|null $streamFactory fabrica PSR-17; implicit descoperita automat
     * @param string|null $userAgent adaugat la `User-Agent`, ex: `"flota-mea/2.1"`
     * @param Ceas|null $ceas doar pentru teste
     *
     * @throws EroareConfigurare lipseste cheia API sau o optiune e invalida
     */
    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::URL_IMPLICIT,
        int $timeoutMs = 30000,
        array|false $retry = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?string $userAgent = null,
        ?Ceas $ceas = null,
    ) {
        if ($timeoutMs <= 0) {
            throw new EroareConfigurare('`timeoutMs` trebuie sa fie pozitiv.');
        }
        $userAgent = trim($userAgent ?? '');

        $setari = new Setari(
            apiKey: self::cheie($apiKey),
            baseUrl: self::adresa($baseUrl),
            retry: $retry === false ? null : SetariRetry::dinOptiuni($retry),
            http: FabricaHttp::client($httpClient, $timeoutMs),
            cereri: FabricaHttp::fabricaCereri($requestFactory),
            fluxuri: FabricaHttp::fabricaFluxuri($streamFactory),
            userAgent: 'verificrca-sdk-php/' . self::VERSIUNE_SDK . ($userAgent !== '' ? ' ' . $userAgent : ''),
            ceas: $ceas ?? new CeasSistem(),
        );
        $http = new Http($setari);

        $this->verificari = new ClientVerificari($http, $setari->ceas);
        $this->vehicule = new ClientVehicule($http);
    }

    private static function cheie(?string $apiKey): string
    {
        $cheie = trim($apiKey ?? self::citesteMediu('VERIFICRCA_API_KEY') ?? '');
        if ($cheie === '') {
            throw new EroareConfigurare(
                'Lipseste cheia API. Trimite `apiKey` sau seteaza variabila de mediu VERIFICRCA_API_KEY.',
                hint: 'Cheia se genereaza din https://www.verificrca.ro/dashboard/api',
            );
        }
        if (strlen($cheie) < 16 || strlen($cheie) > 128) {
            throw new EroareConfigurare('Cheia API are un format invalid (intre 16 si 128 de caractere).');
        }

        return $cheie;
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
