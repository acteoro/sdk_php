<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Auto\ClientAuto;
use Acteo\Exception\EroareConfigurare;
use Acteo\Internal\Ceas;
use Acteo\Internal\Configurare;
use Acteo\Internal\Http;
use Acteo\Internal\ProfilClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Clientul API-ului Acteo, organizat pe domenii: `$acteo->auto->rca`,
 * `$acteo->auto->itp`, `$acteo->auto->rovinieta`, `$acteo->auto->arr`,
 * `$acteo->auto->flota` si `$acteo->webhooks`.
 *
 * Toate metodele arunca subclase de `ActeoException`. Erorile trecatoare
 * (retea, 5xx, throttle la polling) sunt reincercate automat, fara sa repete
 * vreodata o verificare taxata.
 *
 * Exemplu:
 *     $acteo = new Acteo(apiKey: getenv('ACTEO_API_KEY') ?: null);
 *     $rca = $acteo->auto->rca->verifica('B123ABC');
 *     echo $rca->status->value, ' ', $rca->expiresAt?->format('Y-m-d');
 */
final class Acteo
{
    /** Versiunea acestui SDK, trimisa si in headerul `User-Agent`. */
    public const VERSIUNE_SDK = '0.1.0';

    /** Adresa implicita a API-ului. */
    public const URL_IMPLICIT = 'https://api.acteo.ro';

    /** Documentele unei masini (RCA, ITP, rovinieta, ARR) si flota. */
    public readonly ClientAuto $auto;

    /** Citirea webhook-urilor. */
    public readonly ClientWebhooks $webhooks;

    /**
     * Creeaza un client. Nu face nicio cerere pe retea.
     *
     * @param string|null $apiKey cheia API; implicit din variabila de mediu `ACTEO_API_KEY`
     *   (sau `VERIFICRCA_API_KEY`, daca prima lipseste)
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
                variabileCheie: ['ACTEO_API_KEY', 'VERIFICRCA_API_KEY'],
                agent: 'acteo-sdk-php',
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

        $this->auto = new ClientAuto(new Http($setari), $setari->ceas);
        $this->webhooks = new ClientWebhooks();
    }
}
