<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use PHPUnit\Framework\TestCase;
use VerificRca\Cerere\CerereVerificare;
use VerificRca\Cerere\OptiuniAsteptare;
use VerificRca\Cerere\ReferintaVerificare;
use VerificRca\Enum\StatusDocument;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareAutentificare;
use VerificRca\Exception\EroareLimita;
use VerificRca\Exception\EroareRaspunsInvalid;
use VerificRca\Exception\EroareServer;
use VerificRca\Exception\EroareTimeoutVerificare;
use VerificRca\Exception\EroareValidare;
use VerificRca\Exception\EroareVerificare;
use VerificRca\Exception\VerificRcaException;
use VerificRca\Model\VerificareFinala;
use VerificRca\Model\VerificareInCurs;
use VerificRca\Tests\Ajutor\Ajutor;
use VerificRca\Tests\Ajutor\CeasFals;
use VerificRca\Tests\Ajutor\ClientFals;

final class VerificariTest extends TestCase
{
    private const HEADERE_COTA = [
        'X-RateLimit-Limit-Day' => '300',
        'X-RateLimit-Remaining-Day' => '299',
        'X-RateLimit-Limit-Month' => '10000',
        'X-RateLimit-Remaining-Month' => '9876',
    ];

    private const IN_CURS = ['tip' => 'rca', 'query' => 'B123ABC', 'status' => 'processing'];

    public function testRezultatDinCacheCuDateSiCota(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID, self::HEADERE_COTA));
        $r = Ajutor::client($client)->verificari->porneste(new CerereVerificare(TipVerificare::Rca, 'b-123-abc'));

        self::assertInstanceOf(VerificareFinala::class, $r);
        self::assertSame(StatusDocument::Valid, $r->status);
        self::assertSame('2027-03-01', $r->expiresAt?->format('Y-m-d'));
        self::assertNotNull($r->limite);
        self::assertSame(300, $r->limite->zi->limita);
        self::assertSame(299, $r->limite->zi->ramase);
        self::assertSame(10000, $r->limite->luna->limita);
        self::assertSame(9876, $r->limite->luna->ramase);

        $c = $client->cerere();
        self::assertSame('POST', $c->getMethod());
        self::assertSame('/api/public/v1/verificare', $c->getUri()->getPath());
        self::assertSame(['tip' => 'rca', 'query' => 'B123ABC'], $client->corp());
        self::assertStringStartsWith('verificrca-sdk-php/', $c->getHeaderLine('User-Agent'));
    }

    public function testFaraToateHeadereleDeCotaLimiteENull(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID, ['X-RateLimit-Limit-Day' => '300']));
        $r = Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
        self::assertInstanceOf(VerificareFinala::class, $r);
        self::assertNull($r->limite);
    }

    public function testInCoadaSinceVineDinPollUrl(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, [
            'tip' => 'rca',
            'query' => Ajutor::VIN,
            'status' => 'processing',
            'requestId' => 'req_1',
            'queuedAt' => '2026-09-29T10:00:00.000Z',
            'pollUrl' => 'GET /api/public/v1/verificare?tip=rca&query=' . Ajutor::VIN . '&since=1790000000000',
        ]));
        $r = Ajutor::client($client)->verificari->porneste(new CerereVerificare(
            TipVerificare::Rca,
            'B123ABC',
            Ajutor::VIN,
            'https://example.com/hook/secret',
        ));

        self::assertInstanceOf(VerificareInCurs::class, $r);
        self::assertSame(Ajutor::VIN, $r->query);
        self::assertSame(1790000000000, $r->since);
        self::assertSame('req_1', $r->requestId);
        self::assertNull($r->serieSasiu);
        self::assertSame([
            'tip' => 'rca',
            'query' => 'B123ABC',
            'serieSasiu' => Ajutor::VIN,
            'callbackUrl' => 'https://example.com/hook/secret',
        ], $client->corp());
    }

    public function testInCoadaFaraPollUrlSinceEMomentulCererii(): void
    {
        $ceas = new CeasFals(1234);
        $client = new ClientFals(ClientFals::raspuns(202, self::IN_CURS));
        $r = Ajutor::client($client, $ceas)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
        self::assertInstanceOf(VerificareInCurs::class, $r);
        self::assertSame(1234, $r->since);
    }

    public function testRovinietaPastreazaSerieSasiuPentruPolling(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, ['tip' => 'rovinieta', 'query' => 'B123ABC', 'status' => 'processing']));
        $r = Ajutor::client($client)->verificari->porneste(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', Ajutor::VIN, serieTalon: 'i00639603f'));
        self::assertInstanceOf(VerificareInCurs::class, $r);
        self::assertSame(Ajutor::VIN, $r->serieSasiu);
        self::assertSame(
            ['tip' => 'rovinieta', 'query' => 'B123ABC', 'serieSasiu' => Ajutor::VIN, 'serieTalon' => 'I00639603F'],
            $client->corp(),
        );
    }

    public function testSerieTalonNuSeTrimiteLaRca(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, self::IN_CURS));
        Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC', serieTalon: 'I00639603F'));
        self::assertArrayNotHasKey('serieTalon', $client->corp());
    }

    public function testRovinietaFaraSerieTalonNuPleacaPeRetea(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', Ajutor::VIN));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('serieTalon', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testValidareaLocalaNuTrimiteNimic(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare(TipVerificare::Itp, 'B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare) {
        }
        self::assertCount(0, $client->cereri);
    }

    public function test401CuHint(): void
    {
        $client = new ClientFals(ClientFals::raspuns(401, ['error' => 'Autentificare esuata.', 'hint' => 'Transmite API key-ul']));
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareAutentificare $e) {
            self::assertInstanceOf(VerificRcaException::class, $e);
            self::assertSame(401, $e->status);
            self::assertSame('Autentificare esuata.', $e->getMessage());
            self::assertSame('Transmite API key-ul', $e->hint);
        }
    }

    public function testCotaAtinsaFaraReincercare(): void
    {
        $client = new ClientFals(ClientFals::raspuns(429, ['error' => 'Limita atinsa.', 'dailyUsage' => '300/300', 'monthlyUsage' => '4000/10000']));
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareLimita $e) {
            self::assertSame('COTA_ATINSA', $e->cod);
            self::assertSame('300/300', $e->utilizareZi);
        }
        self::assertCount(1, $client->cereri);
    }

    public function testPostCu500NuSeRepeta(): void
    {
        $client = new ClientFals(ClientFals::raspuns(500, ['error' => 'Eroare interna.']));
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareServer) {
        }
        self::assertCount(1, $client->cereri);
    }

    public function testRaspunsCuFormaGresita(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['ceva' => 'altceva']));
        $this->expectException(EroareRaspunsInvalid::class);
        Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC'));
    }

    public function testRezultatTrimiteSinceSiSerieSasiuDoarLaRovinieta(): void
    {
        $client = new ClientFals(
            ClientFals::raspuns(200, ['tip' => 'rovinieta'] + Ajutor::REZULTAT_VALID),
            ClientFals::raspuns(200, Ajutor::REZULTAT_VALID),
        );
        $vrca = Ajutor::client($client);
        $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rovinieta, 'B123ABC', Ajutor::VIN, 42));
        $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC', Ajutor::VIN));

        self::assertSame(['tip' => 'rovinieta', 'query' => 'B123ABC', 'serieSasiu' => Ajutor::VIN, 'since' => '42'], $client->query(0));
        self::assertSame(['tip' => 'rca', 'query' => 'B123ABC'], $client->query(1));
        self::assertSame('GET', $client->cerere()->getMethod());
    }

    public function testRezultatAcceptaVerificareaInCursRefacutaDinJson(): void
    {
        $inCurs = new VerificareInCurs(TipVerificare::Rovinieta, 'B123ABC', Ajutor::VIN, 1790000000000);
        /** @var array<mixed> $date */
        $date = json_decode((string) json_encode($inCurs), true);

        $client = new ClientFals(
            ClientFals::raspuns(200, ['tip' => 'rovinieta'] + Ajutor::REZULTAT_VALID),
            ClientFals::raspuns(200, ['tip' => 'rovinieta'] + Ajutor::REZULTAT_VALID),
        );
        $vrca = Ajutor::client($client);
        $vrca->verificari->rezultat($inCurs);
        $vrca->verificari->rezultat(VerificareInCurs::fromArray($date));

        self::assertSame($client->query(0), $client->query(1));
        self::assertSame('1790000000000', $client->query(1)['since']);
    }

    public function testRezultatStatusErrorDevineEroareVerificare(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, [
            'tip' => 'rca',
            'query' => 'B123ABC',
            'status' => 'error',
            'error' => 'Verificarea nu s-a putut finaliza.',
            'hint' => 'Reia verificarea.',
        ]));
        try {
            Ajutor::client($client)->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareVerificare $e) {
            self::assertSame('Verificarea nu s-a putut finaliza.', $e->getMessage());
            self::assertSame('VERIFICARE_ESUATA', $e->cod);
            self::assertSame('Reia verificarea.', $e->hint);
            self::assertNull($e->codSursa);
        }
    }

    public function testStatusErrorCuCodSpuneCeDateAuFostRespinse(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, [
            'tip' => 'rovinieta',
            'query' => 'B123ABC',
            'status' => 'error',
            'error' => 'Numarul certificatului de inmatriculare nu corespunde vehiculului.',
            'cod' => 'serie_talon_invalida',
        ]));
        try {
            Ajutor::client($client)->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rovinieta, 'B123ABC', Ajutor::VIN));
            self::fail('trebuia sa arunce');
        } catch (EroareVerificare $e) {
            self::assertSame('VERIFICARE_ESUATA', $e->cod);
            self::assertSame('serie_talon_invalida', $e->codSursa);
        }
    }

    public function testVerificaSiAsteaptaInterogheazaPanaLaFinal(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(202, self::IN_CURS + ['pollUrl' => 'GET /api/public/v1/verificare?tip=rca&query=B123ABC&since=5']),
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, Ajutor::REZULTAT_VALID),
        );
        $r = Ajutor::client($client, $ceas)->verificari->verificaSiAsteapta(new CerereVerificare('rca', 'B123ABC'));

        self::assertSame(StatusDocument::Valid, $r->status);
        self::assertCount(3, $client->cereri);
        self::assertSame('5', $client->query(1)['since']);
        self::assertSame([3000, 3000], $ceas->somnuri);
    }

    public function testIntervalulNuCoboaraSub2Secunde(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        Ajutor::client($client, $ceas)->verificari->asteapta(
            new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'),
            new OptiuniAsteptare(intervalMs: 100),
        );
        self::assertSame([2000], $ceas->somnuri);
    }

    public function testDepasesteTimeoutMs(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(202, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
        );
        try {
            Ajutor::client($client, $ceas)->verificari->verificaSiAsteapta(
                new CerereVerificare('rca', 'B123ABC'),
                new OptiuniAsteptare(timeoutMs: 7000),
            );
            self::fail('trebuia sa arunce');
        } catch (EroareTimeoutVerificare $e) {
            self::assertSame('TIMEOUT_VERIFICARE', $e->cod);
            self::assertStringContainsString('7 secunde', $e->getMessage());
        }
        self::assertSame([3000, 3000, 1000], $ceas->somnuri);
        self::assertCount(3, $client->cereri);
    }

    public function testAsteaptaNuDepasesteTermenulPrinReincercari(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(502),
            ClientFals::raspuns(502),
            ClientFals::raspuns(502),
            ClientFals::raspuns(502),
        );
        $vrca = Ajutor::client($client, $ceas, ['incercari' => 3, 'intarziereInitialaMs' => 5000, 'intarziereMaximaMs' => 10000]);
        try {
            $vrca->verificari->asteapta(new ReferintaVerificare(TipVerificare::Rca, 'B123ABC'), new OptiuniAsteptare(timeoutMs: 7000));
            self::fail('trebuia sa arunce');
        } catch (EroareTimeoutVerificare $e) {
            self::assertSame('TIMEOUT_VERIFICARE', $e->cod);
            self::assertInstanceOf(EroareServer::class, $e->getPrevious());
        }
        self::assertLessThanOrEqual(7000, array_sum($ceas->somnuri));
        self::assertCount(1, $client->cereri);
    }

    public function testCallbackUrlCuUtf8InvalidEsteEroareDeValidare(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::client($client)->verificari->porneste(new CerereVerificare('rca', 'B123ABC', null, "https://example.com/\xff"));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('callbackUrl', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }
}
