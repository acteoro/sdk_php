<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Cerere\CererePolitaPad;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\StatusPolitaPad;
use Acteo\Exception\EroareServer;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Exception\EroareVerificare;
use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Tests\Ajutor\CeasFals;
use Acteo\Tests\Ajutor\ClientFals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolitaPadTest extends TestCase
{
    // CNP sintetic, valid doar ca cifra de control. Nu apartine nimanui.
    private const CNP = '1900101400012';
    private const ID = '3f2c8a54-0000-4000-8000-000000000000';

    private const BAZA = [
        'requestId' => self::ID,
        'cnpMascat' => '1*********012',
        'judet' => 'Cluj',
        'localitate' => 'Cluj-Napoca',
        'laData' => '2026-11-03',
    ];
    private const PORNITA = self::BAZA + [
        'status' => 'processing',
        'queuedAt' => '2026-11-03T10:00:00.000Z',
        'pollUrl' => 'GET /api/public/v1/polita-pad?id=' . self::ID,
    ];
    private const IN_CURS = self::BAZA + ['status' => 'processing', 'queuedAt' => '2026-11-03T10:00:00.000Z'];
    private const GATA = self::BAZA + [
        'status' => 'done',
        'valabila' => false,
        'serieNumar' => null,
        'distribuitor' => null,
        'verificatLa' => '2026-11-03T10:02:00.000Z',
    ];
    private const ESUATA = self::BAZA + ['status' => 'error', 'code' => 'UPSTREAM_CHANGED', 'error' => 'Sursa s-a schimbat.'];

    public function testPornesteTrimitePostCuCorpulNormalizatSiIntoarce202(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, self::PORNITA));
        $r = Ajutor::acteo($client)->casa->pad->porneste(
            ['cnp' => '190 0101 400012', 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => true],
            callbackUrl: 'https://example.com/webhooks/pad',
        );

        $c = $client->cerere();
        self::assertSame('POST', $c->getMethod());
        self::assertSame('/api/public/v1/polita-pad', $c->getUri()->getPath());
        self::assertSame([
            'cnp' => self::CNP,
            'judet' => 'Cluj',
            'localitate' => 'Cluj-Napoca',
            'acord' => true,
            'callbackUrl' => 'https://example.com/webhooks/pad',
        ], $client->corp());
        self::assertSame(self::ID, $r->requestId);
        self::assertSame(StatusPolitaPad::Processing, $r->status);
        self::assertSame('1*********012', $r->cnpMascat);
        self::assertInstanceOf(\DateTimeImmutable::class, $r->queuedAt);
    }

    public function testPornesteTrimiteSectorulSiDataDoarCandSuntDate(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, self::PORNITA));
        Ajutor::acteo($client)->casa->pad->porneste(new CererePolitaPad(cnp: self::CNP, judet: 'B', sector: 3, data: '2026-10-01', acord: true));
        self::assertSame(['cnp' => self::CNP, 'judet' => 'B', 'sector' => 3, 'data' => '2026-10-01', 'acord' => true], $client->corp());
    }

    public function testRezultatFaceGetCuIdSiNuPuneCnpulInUrl(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::GATA));
        $r = Ajutor::acteo($client)->casa->pad->rezultat(self::ID);

        $c = $client->cerere();
        self::assertSame('GET', $c->getMethod());
        self::assertSame('/api/public/v1/polita-pad', $c->getUri()->getPath());
        self::assertSame(['id' => self::ID], $client->query());
        self::assertStringNotContainsString(self::CNP, (string) $c->getUri());
        self::assertSame(StatusPolitaPad::Done, $r->status);
        self::assertFalse($r->valabila);
        self::assertInstanceOf(\DateTimeImmutable::class, $r->verificatLa);
    }

    public function testRezultatAcceptaSiRaspunsulDeLaPorneste(): void
    {
        $client = new ClientFals(ClientFals::raspuns(202, self::PORNITA), ClientFals::raspuns(200, self::IN_CURS));
        $acteo = Ajutor::acteo($client);
        $p = $acteo->casa->pad->porneste(new CererePolitaPad(cnp: self::CNP, judet: 'Cluj', localitate: 'Cluj-Napoca', acord: true));
        $r = $acteo->casa->pad->rezultat($p);
        self::assertSame(['id' => self::ID], $client->query(1));
        self::assertSame(StatusPolitaPad::Processing, $r->status);
        self::assertNull($r->valabila);
    }

    public function testAsteaptaRepetaPanaLaDone(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::GATA),
        );
        $r = Ajutor::acteo($client, $ceas)->casa->pad->asteapta(self::ID);

        self::assertSame(StatusPolitaPad::Done, $r->status);
        self::assertFalse($r->valabila);
        self::assertCount(3, $client->cereri);
        self::assertSame([3000, 3000, 3000], $ceas->somnuri);
    }

    public function testVerificaPornesteSiAsteapta(): void
    {
        $client = new ClientFals(
            ClientFals::raspuns(202, self::PORNITA),
            ClientFals::raspuns(200, ['valabila' => true, 'serieNumar' => 'PAD-0001', 'distribuitor' => 'Asigurator'] + self::GATA),
        );
        $r = Ajutor::acteo($client)->casa->pad->verifica(['cnp' => self::CNP, 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => true]);

        self::assertTrue($r->valabila);
        self::assertSame('PAD-0001', $r->serieNumar);
        self::assertSame('Asigurator', $r->distribuitor);
        self::assertCount(2, $client->cereri);
    }

    public function testAsteaptaDepasesteTimeoutMs(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
            ClientFals::raspuns(200, self::IN_CURS),
        );
        try {
            Ajutor::acteo($client, $ceas)->casa->pad->asteapta(self::ID, new OptiuniAsteptare(timeoutMs: 7000));
            self::fail('trebuia sa arunce');
        } catch (EroareTimeoutVerificare $e) {
            self::assertSame('TIMEOUT_VERIFICARE', $e->cod);
            self::assertStringContainsString('7 secunde', $e->getMessage());
        }
        self::assertSame([3000, 3000, 1000], $ceas->somnuri);
    }

    public function testStatusErrorDevineEroareVerificareCuCodulApi(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::ESUATA));
        try {
            Ajutor::acteo($client)->casa->pad->rezultat(self::ID);
            self::fail('trebuia sa arunce');
        } catch (EroareVerificare $e) {
            self::assertSame('UPSTREAM_CHANGED', $e->cod);
            self::assertSame('Sursa s-a schimbat.', $e->getMessage());
        }
    }

    public function testStatusErrorFaraCodDevineVerificareEsuata(): void
    {
        $faraCod = self::ESUATA;
        unset($faraCod['code']);
        $client = new ClientFals(ClientFals::raspuns(200, $faraCod));
        try {
            Ajutor::acteo($client)->casa->pad->rezultat(self::ID);
            self::fail('trebuia sa arunce');
        } catch (EroareVerificare $e) {
            self::assertSame('VERIFICARE_ESUATA', $e->cod);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, ?string, string}> */
    public static function cereriGresite(): iterable
    {
        yield 'CNP gresit' => [['cnp' => '1900101400013', 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => true], null, 'cnp'];
        yield 'fara acord' => [['cnp' => self::CNP, 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => false], null, 'acord'];
        yield 'acord lipsa' => [['cnp' => self::CNP, 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca'], null, 'acord'];
        yield 'data gresita' => [['cnp' => self::CNP, 'judet' => 'Cluj', 'data' => '03.11.2026', 'acord' => true], null, 'data'];
        yield 'data inexistenta' => [['cnp' => self::CNP, 'judet' => 'Cluj', 'data' => '2026-02-30', 'acord' => true], null, 'data'];
        yield 'callbackUrl gresit' => [['cnp' => self::CNP, 'judet' => 'Cluj', 'acord' => true], 'ftp://example.com', 'callbackUrl'];
        yield 'cheie necunoscuta' => [['cnp' => self::CNP, 'judetul' => 'Cluj', 'acord' => true], null, 'identificator'];
    }

    /** @param array<string, mixed> $cerere */
    #[DataProvider('cereriGresite')]
    public function testValidareLocalaFaraRequest(array $cerere, ?string $callbackUrl, string $camp): void
    {
        $client = new ClientFals();
        try {
            // @phpstan-ignore argument.type (testam tocmai valorile gresite)
            Ajutor::acteo($client)->casa->pad->porneste($cerere, $callbackUrl);
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('VALIDARE_LOCALA', $e->cod);
            self::assertSame($camp, $e->camp);
            self::assertStringNotContainsString('1900101400013', $e->getMessage());
        }
        self::assertCount(0, $client->cereri);
    }

    public function testRezultatFaraIdERespinsLocal(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::acteo($client)->casa->pad->rezultat(' ');
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('VALIDARE_LOCALA', $e->cod);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testVerificaNuRepetaPostulLa500(): void
    {
        $client = new ClientFals(ClientFals::raspuns(500, ['error' => 'Eroare interna.']), ClientFals::raspuns(202, self::PORNITA));
        try {
            Ajutor::acteo($client)->casa->pad->verifica(new CererePolitaPad(cnp: self::CNP, judet: 'Cluj', localitate: 'Cluj-Napoca', acord: true));
            self::fail('trebuia sa arunce');
        } catch (EroareServer) {
        }
        self::assertCount(1, $client->cereri);
    }
}
