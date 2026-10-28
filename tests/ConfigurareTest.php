<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Cerere\ReferintaVerificare;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareConfigurare;
use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Tests\Ajutor\ClientFals;
use Acteo\VerificRca;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class ConfigurareTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('VERIFICRCA_API_KEY');
        unset($_ENV['VERIFICRCA_API_KEY']);
    }

    public function testFaraCheie(): void
    {
        putenv('VERIFICRCA_API_KEY');
        try {
            new VerificRca(httpClient: new ClientFals());
            self::fail('trebuia sa arunce');
        } catch (EroareConfigurare $e) {
            self::assertStringContainsString('VERIFICRCA_API_KEY', $e->getMessage());
            self::assertStringContainsString('dashboard/api', (string) $e->hint);
        }
    }

    public function testCitesteCheiaDinMediu(): void
    {
        putenv('VERIFICRCA_API_KEY=' . Ajutor::CHEIE);
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        $fabrica = new HttpFactory();
        $vrca = new VerificRca(httpClient: $client, requestFactory: $fabrica, streamFactory: $fabrica);
        $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'));

        self::assertSame('Bearer ' . Ajutor::CHEIE, $client->cerere()->getHeaderLine('Authorization'));
    }

    public function testCheieCuFormatInvalid(): void
    {
        $this->expectException(EroareConfigurare::class);
        new VerificRca(apiKey: 'scurta', httpClient: new ClientFals());
    }

    public function testBaseUrlInvalid(): void
    {
        $this->expectException(EroareConfigurare::class);
        new VerificRca(apiKey: Ajutor::CHEIE, baseUrl: 'ftp://x', httpClient: new ClientFals());
    }

    public function testTimeoutInvalid(): void
    {
        $this->expectException(EroareConfigurare::class);
        new VerificRca(apiKey: Ajutor::CHEIE, timeoutMs: 0, httpClient: new ClientFals());
    }

    public function testRetryNecunoscut(): void
    {
        $this->expectException(EroareConfigurare::class);
        $this->expectExceptionMessage('incercari');
        new VerificRca(apiKey: Ajutor::CHEIE, retry: ['incercar' => 3], httpClient: new ClientFals());
    }

    public function testUserAgentSiBaseUrlFaraSlashFinal(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        $fabrica = new HttpFactory();
        $vrca = new VerificRca(
            apiKey: Ajutor::CHEIE,
            baseUrl: 'https://staging.example.com/',
            userAgent: 'flota-mea/2.1',
            httpClient: $client,
            requestFactory: $fabrica,
            streamFactory: $fabrica,
        );
        $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'));

        self::assertSame('verificrca-sdk-php/' . VerificRca::VERSIUNE_SDK . ' flota-mea/2.1', $client->cerere()->getHeaderLine('User-Agent'));
        self::assertStringStartsWith('https://staging.example.com/api/public/v1/verificare?', (string) $client->cerere()->getUri());
    }

    public function testFaraClientInjectatFolosesteGuzzle(): void
    {
        self::assertInstanceOf(VerificRca::class, new VerificRca(apiKey: Ajutor::CHEIE));
    }
}
