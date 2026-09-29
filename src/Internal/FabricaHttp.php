<?php

declare(strict_types=1);

namespace VerificRca\Internal;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use VerificRca\Exception\EroareConfigurare;

/**
 * @internal Alege clientul HTTP. PSR-18 nu are timeout per cerere, asa ca atunci
 * cand clientul nu e injectat il construim noi, cu `timeoutMs` aplicat.
 */
final class FabricaHttp
{
    private function __construct() {}

    public static function client(?ClientInterface $client, int $timeoutMs): ClientInterface
    {
        if ($client !== null) {
            return $client;
        }
        $secunde = $timeoutMs / 1000;
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => $secunde,
                'connect_timeout' => min($secunde, 10.0),
                'http_errors' => false,
                // Un redirect ar transforma POST-ul in GET si ar pierde cheia; il raportam ca eroare.
                'allow_redirects' => false,
            ]);
        }
        if (class_exists(Psr18Client::class) && class_exists(HttpClient::class)) {
            return new Psr18Client(HttpClient::create(['timeout' => $secunde, 'max_duration' => $secunde, 'max_redirects' => 0]));
        }
        try {
            return Psr18ClientDiscovery::find();
        } catch (\Throwable $e) {
            throw new EroareConfigurare(
                'Nu am gasit niciun client HTTP PSR-18.',
                hint: 'Instaleaza unul, de exemplu: composer require guzzlehttp/guzzle',
                previous: $e,
            );
        }
    }

    public static function fabricaCereri(?RequestFactoryInterface $fabrica): RequestFactoryInterface
    {
        if ($fabrica !== null) {
            return $fabrica;
        }
        try {
            return Psr17FactoryDiscovery::findRequestFactory();
        } catch (\Throwable $e) {
            throw new EroareConfigurare(
                'Nu am gasit nicio implementare PSR-17 pentru cereri.',
                hint: 'Instaleaza una, de exemplu: composer require guzzlehttp/guzzle',
                previous: $e,
            );
        }
    }

    public static function fabricaFluxuri(?StreamFactoryInterface $fabrica): StreamFactoryInterface
    {
        if ($fabrica !== null) {
            return $fabrica;
        }
        try {
            return Psr17FactoryDiscovery::findStreamFactory();
        } catch (\Throwable $e) {
            throw new EroareConfigurare(
                'Nu am gasit nicio implementare PSR-17 pentru fluxuri.',
                hint: 'Instaleaza una, de exemplu: composer require guzzlehttp/guzzle',
                previous: $e,
            );
        }
    }
}
