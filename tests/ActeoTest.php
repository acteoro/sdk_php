<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Acteo;
use Acteo\Auto\IdentificatorRca;
use Acteo\Auto\IdentificatorRovinieta;
use Acteo\Enum\StatusDocument;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareConfigurare;
use Acteo\Exception\EroareValidare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;
use Acteo\Model\WebhookTest;
use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Tests\Ajutor\ClientFals;
use Acteo\VerificRca;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class ActeoTest extends TestCase
{
    private const REZULTAT_VIN = ['query' => Ajutor::VIN, 'numarInmatriculare' => null, 'serieSasiu' => Ajutor::VIN] + Ajutor::REZULTAT_VALID;

    protected function tearDown(): void
    {
        foreach (['ACTEO_API_KEY', 'VERIFICRCA_API_KEY'] as $nume) {
            putenv($nume);
            unset($_ENV[$nume], $_SERVER[$nume]);
        }
    }

    private static function faraCheie(ClientFals $client): Acteo
    {
        $fabrica = new HttpFactory();

        return new Acteo(httpClient: $client, requestFactory: $fabrica, streamFactory: $fabrica);
    }

    // Configurare

    public function testAdresaImplicitaEApiActeoCuUserAgentActeo(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        Ajutor::acteo($client)->auto->rca->verifica('B123ABC');

        $c = $client->cerere();
        self::assertSame('https://api.acteo.ro/api/public/v1/verificare', (string) $c->getUri());
        self::assertSame('acteo-sdk-php/' . Acteo::VERSIUNE_SDK, $c->getHeaderLine('User-Agent'));
        self::assertSame('0.1.0', Acteo::VERSIUNE_SDK);
    }

    public function testCitesteCheiaDinActeoApiKeyApoiDinVerificrcaApiKey(): void
    {
        putenv('ACTEO_API_KEY=');
        putenv('VERIFICRCA_API_KEY=' . Ajutor::CHEIE);
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => []]));
        self::faraCheie($client)->auto->flota->lista();
        self::assertSame('Bearer ' . Ajutor::CHEIE, $client->cerere()->getHeaderLine('Authorization'));

        putenv('ACTEO_API_KEY=acteo_cheie_0123456789abcdef');
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => []]));
        self::faraCheie($client)->auto->flota->lista();
        self::assertSame('Bearer acteo_cheie_0123456789abcdef', $client->cerere()->getHeaderLine('Authorization'));
    }

    public function testFaraCheieAruncaEroareCareNumesteActeoApiKey(): void
    {
        putenv('ACTEO_API_KEY');
        putenv('VERIFICRCA_API_KEY');
        try {
            self::faraCheie(new ClientFals());
            self::fail('trebuia sa arunce');
        } catch (EroareConfigurare $e) {
            self::assertStringContainsString('ACTEO_API_KEY', $e->getMessage());
            self::assertStringContainsString('app.acteo.ro/api/chei', (string) $e->hint);
        }
    }

    public function testClientulLegacyRamanePeVerificrca(): void
    {
        putenv('ACTEO_API_KEY=acteo_cheie_0123456789abcdef');
        putenv('VERIFICRCA_API_KEY');
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => []]));
        $fabrica = new HttpFactory();
        try {
            // Legacy nu citeste ACTEO_API_KEY.
            new VerificRca(httpClient: $client, requestFactory: $fabrica, streamFactory: $fabrica);
            self::fail('trebuia sa arunce');
        } catch (EroareConfigurare $e) {
            self::assertStringContainsString('VERIFICRCA_API_KEY', $e->getMessage());
        }

        Ajutor::client($client)->vehicule->lista();
        self::assertSame('https://www.verificrca.ro/api/public/v1/vehicule', (string) $client->cerere()->getUri());
        self::assertStringStartsWith('verificrca-sdk-php/', $client->cerere()->getHeaderLine('User-Agent'));
    }

    // Documente

    public function testRcaCuNumarTrimiteNumarulCaQuery(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        $r = Ajutor::acteo($client)->auto->rca->verifica('b-123 abc');

        self::assertSame('POST', $client->cerere()->getMethod());
        self::assertSame(['tip' => 'rca', 'query' => 'B123ABC'], $client->corp());
        self::assertSame(TipVerificare::Rca, $r->tip);
        self::assertSame(StatusDocument::Valid, $r->status);
        self::assertInstanceOf(\DateTimeImmutable::class, $r->expiresAt);
    }

    public function testRcaCuUnTextDe17CaractereIlTrateazaCaVin(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::REZULTAT_VIN));
        Ajutor::acteo($client)->auto->rca->verifica(strtolower(Ajutor::VIN));
        self::assertSame(['tip' => 'rca', 'query' => Ajutor::VIN], $client->corp());
    }

    public function testRcaCuNumarSiVinTrimiteVinInSerieSasiu(): void
    {
        $client = new ClientFals(
            ClientFals::raspuns(200, self::REZULTAT_VIN),
            ClientFals::raspuns(200, self::REZULTAT_VIN),
        );
        $acteo = Ajutor::acteo($client);
        $acteo->auto->rca->verifica(['numar' => 'B123ABC', 'vin' => Ajutor::VIN]);
        $acteo->auto->rca->verifica(new IdentificatorRca(numar: 'B123ABC', vin: Ajutor::VIN));

        $asteptat = ['tip' => 'rca', 'query' => 'B123ABC', 'serieSasiu' => Ajutor::VIN];
        self::assertSame($asteptat, $client->corp(0));
        self::assertSame($asteptat, $client->corp(1));
    }

    public function testRcaFaraIdentificatorERespinsLocalFaraCerere(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::acteo($client)->auto->rca->verifica([]);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('VALIDARE_LOCALA', $e->cod);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testCheieNecunoscutaInIdentificatorERespinsaLocal(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::acteo($client)->auto->rca->verifica(['numr' => 'B123ABC']);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('identificator', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testItpCereVinUnNumarERespinsLocal(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['tip' => 'itp'] + self::REZULTAT_VIN));
        $acteo = Ajutor::acteo($client);
        $r = $acteo->auto->itp->verifica(Ajutor::VIN);
        self::assertSame(['tip' => 'itp', 'query' => Ajutor::VIN], $client->corp());
        self::assertSame(TipVerificare::Itp, $r->tip);

        try {
            $acteo->auto->itp->verifica('B123ABC');
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('VALIDARE_LOCALA', $e->cod);
        }
        self::assertCount(1, $client->cereri);
    }

    public function testArrTrimiteNumarul(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['tip' => 'arr'] + Ajutor::REZULTAT_VALID));
        Ajutor::acteo($client)->auto->arr->verifica('B123ABC');
        self::assertSame(['tip' => 'arr', 'query' => 'B123ABC'], $client->corp());
    }

    public function testRovinietaTrimiteNumarulVinulSiNumarulTalonului(): void
    {
        $raspuns = ['tip' => 'rovinieta', 'serieSasiu' => Ajutor::VIN] + Ajutor::REZULTAT_VALID;
        $client = new ClientFals(ClientFals::raspuns(200, $raspuns), ClientFals::raspuns(200, $raspuns));
        $acteo = Ajutor::acteo($client);
        $acteo->auto->rovinieta->verifica(['numar' => 'B123ABC', 'vin' => Ajutor::VIN, 'serieTalon' => Ajutor::SERIE_TALON]);
        $acteo->auto->rovinieta->verifica(new IdentificatorRovinieta('B123ABC', Ajutor::VIN, Ajutor::SERIE_TALON));

        $asteptat = ['tip' => 'rovinieta', 'query' => 'B123ABC', 'serieSasiu' => Ajutor::VIN, 'serieTalon' => Ajutor::SERIE_TALON];
        self::assertSame($asteptat, $client->corp(0));
        self::assertSame($asteptat, $client->corp(1));
    }

    public function testRovinietaFaraSerieTalonERespinsaLocal(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::acteo($client)->auto->rovinieta->verifica(['numar' => 'B123ABC', 'vin' => Ajutor::VIN]);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('serieTalon', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    // porneste, rezultat, asteapta

    public function testPornesteTrimiteCallbackUrlSiIntoarceVerificareaInCurs(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, [
            'tip' => 'itp',
            'query' => Ajutor::VIN,
            'status' => 'processing',
            'requestId' => 'req_1',
            'pollUrl' => 'GET /api/public/v1/verificare?tip=itp&query=' . Ajutor::VIN . '&since=9',
        ]));
        $r = Ajutor::acteo($client)->auto->itp->porneste(Ajutor::VIN, callbackUrl: 'https://example.com/hook');

        self::assertSame(['tip' => 'itp', 'query' => Ajutor::VIN, 'callbackUrl' => 'https://example.com/hook'], $client->corp());
        self::assertInstanceOf(VerificareInCurs::class, $r);
        self::assertSame(TipVerificare::Itp, $r->tip);
        self::assertSame(9, $r->since);
        self::assertSame('req_1', $r->requestId);
    }

    public function testAsteaptaPrimesteVerificareaInCursSiPastreazaSince(): void
    {
        $inCurs = ['tip' => 'itp', 'query' => Ajutor::VIN, 'status' => 'processing'];
        $client = new ClientFals(
            ClientFals::raspuns(202, $inCurs + ['pollUrl' => 'GET /api/public/v1/verificare?tip=itp&query=' . Ajutor::VIN . '&since=5']),
            ClientFals::raspuns(200, $inCurs),
            ClientFals::raspuns(200, ['tip' => 'itp'] + self::REZULTAT_VIN),
        );
        $acteo = Ajutor::acteo($client);
        $r = $acteo->auto->itp->porneste(Ajutor::VIN);
        self::assertInstanceOf(VerificareInCurs::class, $r);

        $final = $acteo->auto->itp->asteapta($r);
        self::assertSame(StatusDocument::Valid, $final->status);
        self::assertSame('GET', $client->cerere(1)->getMethod());
        self::assertSame('5', $client->query(1)['since'] ?? null);
        self::assertSame('5', $client->query(2)['since'] ?? null);
    }

    public function testVerificareaInCursDeAltTipERespinsaLocal(): void
    {
        $client = new ClientFals();
        $inCurs = new VerificareInCurs(TipVerificare::Itp, Ajutor::VIN, null, 5);
        try {
            Ajutor::acteo($client)->auto->rca->rezultat($inCurs);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('referinta', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testRezultatAcceptaIdentificatorulLaRcaCuVinInterogheazaDupaVin(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::REZULTAT_VIN));
        $r = Ajutor::acteo($client)->auto->rca->rezultat(['numar' => 'B123ABC', 'vin' => Ajutor::VIN]);

        self::assertInstanceOf(VerificareFinala::class, $r);
        self::assertSame('GET', $client->cerere()->getMethod());
        self::assertSame(['tip' => 'rca', 'query' => Ajutor::VIN], $client->query());
    }

    public function testRezultatLaRovinietaTrimiteSiSeriaDeSasiuDarNuTalonul(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['tip' => 'rovinieta', 'serieSasiu' => Ajutor::VIN] + Ajutor::REZULTAT_VALID));
        Ajutor::acteo($client)->auto->rovinieta->rezultat(['numar' => 'b123abc', 'vin' => Ajutor::VIN, 'serieTalon' => Ajutor::SERIE_TALON]);

        $q = $client->query();
        self::assertSame('B123ABC', $q['query'] ?? null);
        self::assertSame(Ajutor::VIN, $q['serieSasiu'] ?? null);
        self::assertArrayNotHasKey('serieTalon', $q);
    }

    // Flota si webhook-uri

    public function testFlotaListaFolosesteEndpointulDeVehicule(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => []]));
        self::assertSame([], Ajutor::acteo($client)->auto->flota->lista());
        self::assertSame('/api/public/v1/vehicule', $client->cerere()->getUri()->getPath());
    }

    public function testWebhooksParseazaRezultatValideazaCorpul(): void
    {
        $corp = json_encode([
            'requestId' => 'req_1',
            'tip' => 'rca',
            'query' => 'B123ABC',
            'serieSasiu' => null,
            'status' => 'valid',
            'result' => Ajutor::REZULTAT_VALID,
            'checkedAt' => Ajutor::REZULTAT_VALID['checkedAt'],
        ], JSON_THROW_ON_ERROR);
        $w = Ajutor::acteo(new ClientFals())->webhooks->parseazaRezultat($corp);
        self::assertSame('req_1', $w->requestId);
    }

    public function testWebhooksVerificaNotificare(): void
    {
        $corp = ['event' => 'webhook.test', 'id' => 'liv_1', 'mesaj' => 'test', 'trimisLa' => '2026-09-29T10:00:00.000Z'];
        $n = Ajutor::acteo(new ClientFals())->webhooks->verificaNotificare($corp, ['X-Verificrca-Key' => 'whsec_abc'], 'whsec_abc');
        self::assertInstanceOf(WebhookTest::class, $n);
    }
}
