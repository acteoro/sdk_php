<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Enum\CampFirma;
use Acteo\Enum\OficiuMarca;
use Acteo\Enum\PragMarca;
use Acteo\Enum\StatusWebhook;
use Acteo\Enum\StatutAsigurare;
use Acteo\Exception\EroareWebhook;
use Acteo\Model\WebhookAsigurareSchimbare;
use Acteo\Model\WebhookDocumentExpira;
use Acteo\Model\WebhookDosarEveniment;
use Acteo\Model\WebhookDosarTermen;
use Acteo\Model\WebhookFirmaDosar;
use Acteo\Model\WebhookFirmaSchimbare;
use Acteo\Model\WebhookMarcaExpira;
use Acteo\Model\WebhookMarcaSchimbare;
use Acteo\Model\WebhookNotificare;
use Acteo\Model\WebhookTermenExpira;
use Acteo\Model\WebhookTest;
use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Webhooks;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const CHEIE = 'whsec_abc123';

    private const EXPIRA = [
        'event' => 'document.expira',
        'id' => 'liv_1',
        'vehicul' => ['id' => 'v1', 'numarInmatriculare' => 'B123ABC', 'serieSasiu' => null],
        'document' => 'itp',
        'expiraLa' => '2026-10-10T00:00:00.000Z',
        'zileRamase' => 11,
        'prag' => 14,
        'trimisLa' => '2026-09-29T10:00:00.000Z',
    ];

    public function testParseazaRezultatulDinString(): void
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
        $w = Webhooks::parseazaWebhookRezultat($corp);
        self::assertSame('req_1', $w->requestId);
        self::assertSame('2027-03-01', $w->result?->expiresAt?->format('Y-m-d'));
    }

    public function testParseazaRezultatulDeEroareDinArray(): void
    {
        $w = Webhooks::parseazaWebhookRezultat([
            'requestId' => 'req_2',
            'tip' => 'itp',
            'query' => Ajutor::VIN,
            'serieSasiu' => Ajutor::VIN,
            'status' => 'error',
            'result' => null,
            'checkedAt' => null,
            'error' => 'Verificarea nu s-a putut finaliza.',
        ]);
        self::assertSame(StatusWebhook::Error, $w->status);
    }

    public function testRespingeJsonInvalid(): void
    {
        $e = $this->eroare(static fn() => Webhooks::parseazaWebhookRezultat('{nu e json'));
        self::assertSame('JSON_INVALID', $e->cod);
    }

    public function testRespingePayloadInvalid(): void
    {
        $e = $this->eroare(static fn() => Webhooks::parseazaWebhookRezultat(['requestId' => 'x']));
        self::assertSame('PAYLOAD_INVALID', $e->cod);
        self::assertStringStartsWith('Payload webhook invalid: ', $e->getMessage());

        $e2 = $this->eroare(static fn() => Webhooks::parseazaWebhookRezultat('"doar un text"'));
        self::assertSame('PAYLOAD_INVALID', $e2->cod);
    }

    public function testAcceptaNotificareaDinArrayDeHeadere(): void
    {
        $n = Webhooks::verificaWebhookNotificare(json_encode(self::EXPIRA, JSON_THROW_ON_ERROR), ['X-Acteo-Key' => self::CHEIE], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
        self::assertSame(14, $n->prag);
        self::assertSame('2026-10-10', $n->expiraLa->format('Y-m-d'));
    }

    public function testAcceptaHeadereleInFormaLaravel(): void
    {
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-acteo-key' => [self::CHEIE]], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaHeadereleDinServer(): void
    {
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, ['HTTP_X_ACTEO_KEY' => self::CHEIE, 'REQUEST_METHOD' => 'POST'], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaCererePsr7(): void
    {
        $cerere = new ServerRequest('POST', 'https://example.com/hook', ['X-Acteo-Key' => self::CHEIE]);
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, $cerere, self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaEvenimentulDeTest(): void
    {
        $n = Webhooks::verificaWebhookNotificare(
            ['event' => 'webhook.test', 'id' => 't1', 'mesaj' => 'Test', 'trimisLa' => '2026-09-29T10:00:00.000Z'],
            ['X-Acteo-Key' => 'k'],
            'k',
        );
        self::assertInstanceOf(WebhookTest::class, $n);
    }

    public function testRespingeCheiaGresitaSauLipsa(): void
    {
        self::assertSame('CHEIE_INVALIDA', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-acteo-key' => 'gresit'], self::CHEIE))->cod);

        $lipsa = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, [], self::CHEIE));
        self::assertSame('CHEIE_INVALIDA', $lipsa->cod);
        self::assertStringContainsString('X-Acteo-Key', $lipsa->getMessage());

        self::assertSame('CHEIE_NECONFIGURATA', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-acteo-key' => ''], ''))->cod);
    }

    public function testEvenimentNecunoscut(): void
    {
        $e = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(['event' => 'altceva', 'id' => 'x'], ['x-acteo-key' => 'k'], 'k'));
        self::assertSame('PAYLOAD_INVALID', $e->cod);
    }

    public function testHeadereleSuntXActeo(): void
    {
        self::assertSame('x-acteo-key', Webhooks::HEADER_CHEIE);
        self::assertSame('x-acteo-event', Webhooks::HEADER_EVENIMENT);
    }

    public function testCitesteSiHeaderulVechiCandLipsesteCelNou(): void
    {
        self::assertInstanceOf(WebhookDocumentExpira::class, Webhooks::verificaWebhookNotificare(self::EXPIRA, ['X-Verificrca-Key' => self::CHEIE], self::CHEIE));
        self::assertInstanceOf(WebhookDocumentExpira::class, Webhooks::verificaWebhookNotificare(self::EXPIRA, ['HTTP_X_VERIFICRCA_KEY' => self::CHEIE], self::CHEIE));
        $psr = new ServerRequest('POST', 'https://example.com/hook', ['X-Verificrca-Key' => self::CHEIE]);
        self::assertInstanceOf(WebhookDocumentExpira::class, Webhooks::verificaWebhookNotificare(self::EXPIRA, $psr, self::CHEIE));

        // Headerul nou are prioritate: o cheie veche corecta nu salveaza una noua gresita.
        $e = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, ['X-Acteo-Key' => 'gresit', 'X-Verificrca-Key' => self::CHEIE], self::CHEIE));
        self::assertSame('CHEIE_INVALIDA', $e->cod);
    }

    /** @return array<string, array{0: class-string, 1: array<string, mixed>}> */
    public static function evenimente(): array
    {
        $comun = ['id' => 'liv_2', 'trimisLa' => '2026-10-30T10:00:00.000Z'];

        return [
            'dosar.eveniment' => [WebhookDosarEveniment::class, ['event' => 'dosar.eveniment'] + $comun + [
                'dosar' => ['id' => 'd1', 'numar' => '1234/3/2024', 'eticheta' => null],
                'eveniment' => ['tip' => 'termen_nou', 'descriere' => 'Termen nou', 'institutie' => 'Tribunalul Bucuresti'],
            ]],
            'dosar.termen' => [WebhookDosarTermen::class, ['event' => 'dosar.termen'] + $comun + [
                'dosar' => ['id' => 'd1', 'numar' => '1234/3/2024', 'eticheta' => 'Litigiu'],
                'sedinta' => ['data' => '2026-11-02T00:00:00.000Z', 'ora' => '09:00', 'institutie' => null, 'complet' => 'C1'],
                'zileRamase' => 3,
            ]],
            'firma.schimbare' => [WebhookFirmaSchimbare::class, ['event' => 'firma.schimbare'] + $comun + [
                'firma' => ['id' => 'f1', 'denumire' => 'Exemplu SRL'],
                'schimbari' => [['camp' => 'platitor_tva', 'valoareVeche' => 'nu', 'valoareNoua' => 'da', 'dataEfect' => '2026-11-01']],
            ]],
            'firma.dosar' => [WebhookFirmaDosar::class, ['event' => 'firma.dosar'] + $comun + [
                'firma' => ['id' => 'f1', 'denumire' => 'Exemplu SRL'],
                'dosar' => ['numar' => '99/2/2026', 'institutie' => 'Tribunalul Cluj', 'calitateParte' => 'Parat', 'obiect' => null, 'categorie' => null, 'insolventa' => null],
            ]],
            'marca.schimbare' => [WebhookMarcaSchimbare::class, ['event' => 'marca.schimbare'] + $comun + [
                'marca' => ['id' => 'm1', 'st13' => 'RO500000000000001', 'nume' => 'EXEMPLU', 'oficiu' => 'RO', 'numarDepozit' => 'M 2020 00001'],
                'schimbare' => 'reinnoita',
                'valoareVeche' => '2026-11-01',
                'valoareNoua' => '2036-11-01',
            ]],
            'marca.expira' => [WebhookMarcaExpira::class, ['event' => 'marca.expira'] + $comun + [
                'marca' => ['id' => 'm1', 'st13' => 'EM500000000000001', 'nume' => 'EXEMPLU', 'oficiu' => 'EM', 'numarDepozit' => '000000001'],
                'expiraLa' => '2027-01-01',
                'prag' => '3l',
                'reinnoirePanaLa' => null,
            ]],
            'asigurare.schimbare' => [WebhookAsigurareSchimbare::class, ['event' => 'asigurare.schimbare'] + $comun + [
                'persoana' => ['id' => 'p1', 'nume' => 'Ion Popescu'],
                'statut' => 'neasigurat',
                'areCard' => null,
                'verificatLa' => '2026-10-30T09:00:00.000Z',
            ]],
            'termen.expira' => [WebhookTermenExpira::class, ['event' => 'termen.expira'] + $comun + [
                'termen' => ['id' => 't1', 'categorie' => 'personale', 'tip' => 'pasaport', 'eticheta' => 'Pasaport', 'al' => null, 'expiraLa' => '2026-11-29'],
                'zileRamase' => 30,
                'prag' => 30,
            ]],
        ];
    }

    /**
     * @param class-string $clasa
     * @param array<string, mixed> $corp
     */
    #[DataProvider('evenimente')]
    public function testAcceptaEvenimentulCelorlalteServicii(string $clasa, array $corp): void
    {
        $n = Webhooks::verificaWebhookNotificare(json_encode($corp, JSON_THROW_ON_ERROR), ['x-acteo-key' => 'k'], 'k');
        self::assertInstanceOf($clasa, $n);
        self::assertInstanceOf(WebhookNotificare::class, $n);
        $campuri = get_object_vars($n);
        self::assertSame($corp['event'], $campuri['event'] ?? null);
        $trimisLa = $campuri['trimisLa'] ?? null;
        self::assertInstanceOf(\DateTimeImmutable::class, $trimisLa);
        self::assertSame('2026-10-30T10:00:00+00:00', $trimisLa->format(DATE_ATOM));
    }

    public function testDateleIsoDevinDateCeleCalendaristiceRamanText(): void
    {
        $termen = Webhooks::verificaWebhookNotificare(self::evenimente()['dosar.termen'][1], ['x-acteo-key' => 'k'], 'k');
        self::assertInstanceOf(WebhookDosarTermen::class, $termen);
        self::assertSame('2026-11-02', $termen->sedinta->data->format('Y-m-d'));
        self::assertSame(3, $termen->zileRamase);

        $marca = Webhooks::verificaWebhookNotificare(self::evenimente()['marca.expira'][1], ['x-acteo-key' => 'k'], 'k');
        self::assertInstanceOf(WebhookMarcaExpira::class, $marca);
        self::assertSame('2027-01-01', $marca->expiraLa);
        self::assertSame(PragMarca::TreiLuni, $marca->prag);
        self::assertSame(OficiuMarca::Em, $marca->marca->oficiu);

        $firma = Webhooks::verificaWebhookNotificare(self::evenimente()['firma.schimbare'][1], ['x-acteo-key' => 'k'], 'k');
        self::assertInstanceOf(WebhookFirmaSchimbare::class, $firma);
        self::assertSame(CampFirma::PlatitorTva, $firma->schimbari[0]->camp);
        self::assertSame('2026-11-01', $firma->schimbari[0]->dataEfect);

        $asigurare = Webhooks::verificaWebhookNotificare(self::evenimente()['asigurare.schimbare'][1], ['x-acteo-key' => 'k'], 'k');
        self::assertInstanceOf(WebhookAsigurareSchimbare::class, $asigurare);
        self::assertSame(StatutAsigurare::Neasigurat, $asigurare->statut);
        self::assertNull($asigurare->areCard);
        self::assertSame('09:00', $asigurare->verificatLa->format('H:i'));
    }

    public function testValideazaEnumurileEvenimentelorNoi(): void
    {
        $termen = self::evenimente()['dosar.termen'][1];
        $termen['zileRamase'] = 2;
        self::assertSame('PAYLOAD_INVALID', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare($termen, ['x-acteo-key' => 'k'], 'k'))->cod);

        $marca = self::evenimente()['marca.expira'][1];
        $marca['prag'] = '2l';
        self::assertSame('PAYLOAD_INVALID', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare($marca, ['x-acteo-key' => 'k'], 'k'))->cod);

        $dosar = self::evenimente()['dosar.eveniment'][1];
        $dosar['eveniment'] = ['tip' => 'altceva', 'descriere' => 'x', 'institutie' => null];
        $e = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare($dosar, ['x-acteo-key' => 'k'], 'k'));
        self::assertStringContainsString('eveniment.tip', $e->getMessage());
    }

    /** @param callable(): mixed $fn */
    private function eroare(callable $fn): EroareWebhook
    {
        try {
            $fn();
        } catch (EroareWebhook $e) {
            return $e;
        }
        self::fail('trebuia sa arunce EroareWebhook');
    }
}
