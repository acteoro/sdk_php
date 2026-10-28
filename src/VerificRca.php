<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Exception\EroareConfigurare;
use Acteo\Internal\Ceas;
use Acteo\Internal\Configurare;
use Acteo\Internal\Http;
use Acteo\Internal\ProfilClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Clientul legacy, pastrat pentru codul existent; pentru cod nou foloseste `Acteo`.
 * Verificare RCA, ITP, rovinieta si ARR, plus gestionarea vehiculelor monitorizate.
 *
 * Toate metodele arunca subclase de `ActeoException`. Erorile trecatoare
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
    public const VERSIUNE_SDK = Acteo::VERSIUNE_SDK;

    /** Adresa implicita a clientului legacy. */
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
        $setari = Configurare::setari(
            new ProfilClient(
                urlImplicit: self::URL_IMPLICIT,
                variabileCheie: ['VERIFICRCA_API_KEY'],
                agent: 'verificrca-sdk-php',
                undeCheie: 'https://www.verificrca.ro/dashboard/api',
            ),
            self::VERSIUNE_SDK,
            $apiKey,
            $baseUrl,
            $timeoutMs,
            $retry,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $userAgent,
            $ceas,
        );
        $http = new Http($setari);

        $this->verificari = new ClientVerificari($http, $setari->ceas);
        $this->vehicule = new ClientVehicule($http);
    }
}
