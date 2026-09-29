<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use VerificRca\Exception\EroareAutentificare;
use VerificRca\Exception\EroareConflict;
use VerificRca\Exception\EroareHttp;
use VerificRca\Exception\EroareInterzis;
use VerificRca\Exception\EroareLimita;
use VerificRca\Exception\EroareNegasit;
use VerificRca\Exception\EroarePlanInactiv;
use VerificRca\Exception\EroareRaspunsInvalid;
use VerificRca\Exception\EroareRetea;
use VerificRca\Exception\EroareServer;
use VerificRca\Exception\EroareValidare;
use VerificRca\Exception\VerificRcaException;
use VerificRca\Internal\Decodor;
use VerificRca\Internal\Erori;
use VerificRca\Internal\FabricaHttp;
use VerificRca\Internal\Http;
use VerificRca\Internal\Raspuns;
use VerificRca\Internal\SetariRetry;
use VerificRca\Tests\Ajutor\Ajutor;
use VerificRca\Tests\Ajutor\CeasFals;
use VerificRca\Tests\Ajutor\ClientFals;
use VerificRca\Tests\Ajutor\ExceptieRetea;

final class HttpTest extends TestCase
{
    public function testGetConstruiesteUrlulSiHeaderele(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['ok' => true], ['X-Ceva' => 'da']));
        $r = (new Http(Ajutor::setari($client)))->trimite('GET', '/api/public/v1/verificare', true, ['tip' => 'rca', 'query' => 'B123ABC', 'since' => null]);

        $c = $client->cerere();
        self::assertSame('GET', $c->getMethod());
        self::assertSame('https://www.verificrca.ro/api/public/v1/verificare?tip=rca&query=B123ABC', (string) $c->getUri());
        self::assertSame('Bearer ' . Ajutor::CHEIE, $c->getHeaderLine('Authorization'));
        self::assertSame('application/json', $c->getHeaderLine('Accept'));
        self::assertSame('verificrca-sdk-php/test', $c->getHeaderLine('User-Agent'));
        self::assertFalse($c->hasHeader('Content-Type'));
        self::assertSame(['ok' => true], $r->corp);
        self::assertSame('da', $r->header('x-ceva'));
    }

    public function testPostTrimiteJson(): void
    {
        $client = new ClientFals(ClientFals::raspuns(201, ['id' => 'x']));
        (new Http(Ajutor::setari($client)))->trimite('POST', '/api/public/v1/vehicule', false, [], ['numarInmatriculare' => 'B123ABC', 'arrMonitorizat' => false]);

        self::assertSame('application/json', $client->cerere()->getHeaderLine('Content-Type'));
        self::assertSame(['numarInmatriculare' => 'B123ABC', 'arrMonitorizat' => false], $client->corp());
    }

    public function testCorpNonJsonPe2xx(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, '<html>ok</html>'));
        try {
            (new Http(Ajutor::setari($client)))->trimite('GET', '/x', true);
            self::fail('trebuia sa arunce');
        } catch (EroareRaspunsInvalid $e) {
            self::assertSame(200, $e->status);
            self::assertSame('<html>ok</html>', $e->raspuns);
        }
    }

    public function testCorpHtmlPe502EsteReincercatSiPastrat(): void
    {
        $client = new ClientFals(
            ClientFals::raspuns(502, '<html>Bad Gateway</html>'),
            ClientFals::raspuns(502, '<html>Bad Gateway</html>'),
            ClientFals::raspuns(502, '<html>Bad Gateway</html>'),
            ClientFals::raspuns(502, '<html>Bad Gateway</html>'),
        );
        try {
            (new Http(Ajutor::setari($client)))->trimite('GET', '/x', true);
            self::fail('trebuia sa arunce');
        } catch (EroareServer $e) {
            self::assertSame(502, $e->status);
            self::assertSame('<html>Bad Gateway</html>', $e->raspuns);
            self::assertSame('Cererea a esuat cu HTTP 502.', $e->getMessage());
        }
        self::assertCount(4, $client->cereri);
    }

    public function testMapareaStatusurilor(): void
    {
        $cazuri = [
            [400, ['error' => 'Tip invalid.', 'accepted' => ['rca', 'itp']], EroareValidare::class],
            [401, ['error' => 'Autentificare esuata.', 'hint' => 'Transmite API key-ul'], EroareAutentificare::class],
            [402, ['error' => 'Fara abonament.'], EroarePlanInactiv::class],
            [403, ['error' => 'Limita.', 'code' => 'LIMIT_ATINS', 'limita' => 5], EroareInterzis::class],
            [404, ['error' => 'Vehicul negasit.', 'code' => 'NEGASIT'], EroareNegasit::class],
            [409, ['error' => 'Exista deja.', 'code' => 'VEHICUL_EXISTENT'], EroareConflict::class],
            [418, ['error' => 'Ceainic.'], EroareHttp::class],
            [500, ['error' => 'Eroare interna.'], EroareServer::class],
        ];
        foreach ($cazuri as [$status, $corp, $clasa]) {
            $e = Erori::dinRaspuns($status, [], $corp, 0);
            self::assertInstanceOf($clasa, $e, "HTTP {$status}");
            self::assertSame($status, $e->status);
            self::assertSame($corp['error'], $e->getMessage());
            self::assertSame($corp, $e->raspuns);
        }

        $v = Erori::dinRaspuns(400, [], ['error' => 'x', 'accepted' => ['rca', 3, 'itp']], 0);
        self::assertInstanceOf(EroareValidare::class, $v);
        self::assertSame(['rca', 'itp'], $v->acceptate);

        $a = Erori::dinRaspuns(401, [], ['error' => 'x', 'hint' => 'Transmite API key-ul'], 0);
        self::assertSame('Transmite API key-ul', $a->hint);

        $i = Erori::dinRaspuns(403, [], ['error' => 'x', 'code' => 'LIMIT_ATINS', 'limita' => 5], 0);
        self::assertInstanceOf(EroareInterzis::class, $i);
        self::assertSame(5, $i->limita);
        self::assertSame('LIMIT_ATINS', $i->cod);
    }

    public function testLimita429(): void
    {
        $cota = Erori::dinRaspuns(429, [], ['error' => 'Limita atinsa.', 'dailyUsage' => '300/300', 'monthlyUsage' => '4000/10000'], 0);
        self::assertInstanceOf(EroareLimita::class, $cota);
        self::assertSame('COTA_ATINSA', $cota->cod);
        self::assertSame('300/300', $cota->utilizareZi);
        self::assertSame('4000/10000', $cota->utilizareLuna);

        $flota = Erori::dinRaspuns(429, [], ['error' => 'x', 'code' => 'LIMITA_FLOTA_ATINSA'], 0);
        self::assertSame('LIMITA_FLOTA_ATINSA', $flota->cod);

        $throttle = Erori::dinRaspuns(429, ['retry-after' => '2'], ['error' => 'Prea multe cereri de polling.'], 0);
        self::assertInstanceOf(EroareLimita::class, $throttle);
        self::assertSame('PREA_MULTE_CERERI', $throttle->cod);
        self::assertSame(2000, $throttle->retryAfterMs);
    }

    public function testRetryAfter(): void
    {
        self::assertNull(Erori::retryAfterMs(null, 0));
        self::assertNull(Erori::retryAfterMs('maine', 0));
        self::assertSame(1500, Erori::retryAfterMs('1.5', 0));
        self::assertSame(0, Erori::retryAfterMs('-3', 0));
        $acum = (new \DateTimeImmutable('2026-09-29T10:00:00Z'))->getTimestamp() * 1000;
        self::assertSame(5000, Erori::retryAfterMs('Tue, 29 Sep 2026 10:00:05 GMT', $acum));
    }

    public function testPostCu500NuSeRepeta(): void
    {
        $client = new ClientFals(ClientFals::raspuns(500, ['error' => 'Eroare interna.']));
        $this->expectException(EroareServer::class);
        try {
            (new Http(Ajutor::setari($client)))->trimite('POST', '/x', false, [], ['a' => 1]);
        } finally {
            self::assertCount(1, $client->cereri);
        }
    }

    public function testPostCu503SeRepeta(): void
    {
        $client = new ClientFals(ClientFals::raspuns(503), ClientFals::raspuns(200, ['ok' => true]));
        (new Http(Ajutor::setari($client)))->trimite('POST', '/x', false, [], ['a' => 1]);
        self::assertCount(2, $client->cereri);
    }

    public function testPostCuConexiuneRefuzataSeRepetaCuResetNu(): void
    {
        $c1 = new ClientFals(new ExceptieRetea('Failed to connect to example.com port 443: Connection refused'), ClientFals::raspuns(200, []));
        (new Http(Ajutor::setari($c1)))->trimite('POST', '/x', false, [], ['a' => 1]);
        self::assertCount(2, $c1->cereri);

        $c2 = new ClientFals(new ExceptieRetea('Connection reset by peer'));
        try {
            (new Http(Ajutor::setari($c2)))->trimite('POST', '/x', false, [], ['a' => 1]);
            self::fail('trebuia sa arunce');
        } catch (EroareRetea $e) {
            self::assertFalse($e->neajunsa);
            self::assertFalse($e->timeout);
        }
        self::assertCount(1, $c2->cereri);
    }

    public function testErrnoCurlDinGuzzle(): void
    {
        $dns = new ConnectException('cURL error 6: see https://curl.haxx.se/libcurl/c/libcurl-errors.html', new Request('POST', 'https://example.com'), null, ['errno' => 6]);
        $c1 = new ClientFals($dns, ClientFals::raspuns(200, []));
        (new Http(Ajutor::setari($c1)))->trimite('POST', '/x', false, [], ['a' => 1]);
        self::assertCount(2, $c1->cereri);

        $timeout = new ConnectException('cURL error 28: Operation timed out after 30001 milliseconds', new Request('POST', 'https://example.com'), null, ['errno' => 28]);
        $c2 = new ClientFals($timeout);
        try {
            (new Http(Ajutor::setari($c2)))->trimite('POST', '/x', false, [], ['a' => 1]);
            self::fail('trebuia sa arunce');
        } catch (EroareRetea $e) {
            self::assertTrue($e->timeout);
            self::assertFalse($e->neajunsa);
            self::assertSame($timeout, $e->getPrevious());
        }
        self::assertCount(1, $c2->cereri);
    }

    public function testGetCu5xxSiThrottleRespectaRetryAfter(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(502),
            ClientFals::raspuns(429, ['error' => 'Prea multe cereri de polling.'], ['Retry-After' => '2']),
            ClientFals::raspuns(200, ['ok' => true]),
        );
        (new Http(Ajutor::setari($client, $ceas, new SetariRetry(3, 1, 5000))))->trimite('GET', '/x', true);

        self::assertCount(3, $client->cereri);
        self::assertCount(2, $ceas->somnuri);
        self::assertGreaterThanOrEqual(2000, $ceas->somnuri[1]);
    }

    public function testThrottleCuRetryAfterPreaMareNuSeReincearca(): void
    {
        $client = new ClientFals(ClientFals::raspuns(429, ['error' => 'x'], ['Retry-After' => '60']));
        $this->expectException(EroareLimita::class);
        (new Http(Ajutor::setari($client, null, new SetariRetry(3, 1, 5000))))->trimite('GET', '/x', true);
    }

    public function testRenuntaDupaIncercari(): void
    {
        $client = new ClientFals(ClientFals::raspuns(500), ClientFals::raspuns(500), ClientFals::raspuns(500), ClientFals::raspuns(500));
        try {
            (new Http(Ajutor::setari($client)))->trimite('GET', '/x', true);
            self::fail('trebuia sa arunce');
        } catch (EroareServer) {
        }
        self::assertCount(4, $client->cereri);
    }

    public function testFaraRetryOSinguraIncercare(): void
    {
        $client = new ClientFals(ClientFals::raspuns(503));
        try {
            (new Http(Ajutor::setari($client, null, null)))->trimite('GET', '/x', true);
            self::fail('trebuia sa arunce');
        } catch (EroareServer) {
        }
        self::assertCount(1, $client->cereri);
    }

    public function testPauzaEPlafonataSiNuCoboaraSubRetryAfter(): void
    {
        $retry = new SetariRetry(10, 500, 10000);
        $server = new EroareServer('x', 500);
        for ($n = 0; $n < 10; ++$n) {
            $baza = min(500 * 2 ** $n, 10000);
            $p = Http::pauza($retry, $n, $server);
            self::assertGreaterThanOrEqual((int) floor($baza * 0.8), $p);
            self::assertLessThanOrEqual(10000, $p);
        }
        $limita = new EroareLimita('x', 429, 'PREA_MULTE_CERERI', retryAfterMs: 7000);
        self::assertGreaterThanOrEqual(7000, Http::pauza($retry, 0, $limita));
    }

    public function testSePoateReincerca(): void
    {
        $r = new SetariRetry(3, 500, 10000);
        self::assertTrue(Http::sePoateReincerca(true, new EroareRetea('x'), $r));
        self::assertFalse(Http::sePoateReincerca(false, new EroareRetea('x'), $r));
        self::assertTrue(Http::sePoateReincerca(false, new EroareRetea('x', neajunsa: true), $r));
        self::assertTrue(Http::sePoateReincerca(false, new EroareServer('x', 504), $r));
        self::assertFalse(Http::sePoateReincerca(false, new EroareServer('x', 500), $r));
        self::assertFalse(Http::sePoateReincerca(true, new EroareLimita('x', 429, 'COTA_ATINSA'), $r));
        self::assertTrue(Http::sePoateReincerca(true, new EroareLimita('x', 429, 'PREA_MULTE_CERERI', retryAfterMs: 2000), $r));
        self::assertFalse(Http::sePoateReincerca(false, new EroareLimita('x', 429, 'PREA_MULTE_CERERI'), $r));
        self::assertFalse(Http::sePoateReincerca(true, new VerificRcaException('x'), $r));
    }

    public function testDecodeaza(): void
    {
        self::assertSame('B', Http::decodeaza(new Raspuns(200, [], ['q' => 'B']), static fn(array $d): mixed => $d['q']));

        try {
            Http::decodeaza(new Raspuns(200, [], ['q' => 1]), static fn(array $d): string => Decodor::sir($d, 'q', ''));
            self::fail('trebuia sa arunce');
        } catch (EroareRaspunsInvalid $e) {
            self::assertStringContainsString('q: se astepta text', $e->getMessage());
            self::assertSame(['q' => 1], $e->raspuns);
        }

        $this->expectException(EroareRaspunsInvalid::class);
        Http::decodeaza(new Raspuns(200, [], null), static fn(array $d): mixed => $d);
    }

    public function testCorpCuUtf8InvalidDevineEroareValidareFaraCerere(): void
    {
        $client = new ClientFals();
        try {
            (new Http(Ajutor::setari($client)))->trimite('POST', '/x', false, [], ['numarInmatriculare' => "B\xff"]);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('VALIDARE_LOCALA', $e->cod);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testRedirectulDevineEroareHttpCuHint(): void
    {
        $e = Erori::dinRaspuns(301, ['location' => 'https://www.verificrca.ro/api/public/v1/vehicule'], '', 0);
        self::assertInstanceOf(EroareHttp::class, $e);
        self::assertStringContainsString('https://www.verificrca.ro', (string) $e->hint);
    }

    public function testClientulConstruitDeSdkNuUrmeazaRedirecturi(): void
    {
        $client = FabricaHttp::client(null, 30000);
        self::assertInstanceOf(\GuzzleHttp\Client::class, $client);
        self::assertFalse($client->getConfig('allow_redirects'));
    }
}
