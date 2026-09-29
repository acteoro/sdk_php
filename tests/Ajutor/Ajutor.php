<?php

declare(strict_types=1);

namespace VerificRca\Tests\Ajutor;

use GuzzleHttp\Psr7\HttpFactory;
use VerificRca\Internal\Setari;
use VerificRca\Internal\SetariRetry;
use VerificRca\VerificRca;

final class Ajutor
{
    public const CHEIE = 'vrca_test_0123456789abcdef';
    public const VIN = 'WVWZZZ1JZXW000001';
    public const REZULTAT_VALID = [
        'tip' => 'rca',
        'query' => 'B123ABC',
        'numarInmatriculare' => 'B123ABC',
        'serieSasiu' => null,
        'checkedAt' => '2026-09-20T10:00:00.000Z',
        'status' => 'valid',
        'expiresAt' => '2027-03-01T00:00:00.000Z',
    ];

    public static function setari(ClientFals $client, ?CeasFals $ceas = null, ?SetariRetry $retry = new SetariRetry(3, 1, 5)): Setari
    {
        $fabrica = new HttpFactory();

        return new Setari(self::CHEIE, 'https://www.verificrca.ro', $retry, $client, $fabrica, $fabrica, 'verificrca-sdk-php/test', $ceas ?? new CeasFals());
    }

    /** @param array{incercari?: int, intarziereInitialaMs?: int, intarziereMaximaMs?: int}|false $retry */
    public static function client(
        ClientFals $client,
        ?CeasFals $ceas = null,
        array|false $retry = ['incercari' => 3, 'intarziereInitialaMs' => 1, 'intarziereMaximaMs' => 5],
    ): VerificRca {
        $fabrica = new HttpFactory();

        return new VerificRca(
            apiKey: self::CHEIE,
            retry: $retry,
            httpClient: $client,
            requestFactory: $fabrica,
            streamFactory: $fabrica,
            ceas: $ceas ?? new CeasFals(),
        );
    }
}
