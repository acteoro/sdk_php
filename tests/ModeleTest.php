<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VerificRca\Enum\StatusDocument;
use VerificRca\Enum\StatusWebhook;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareValidare;
use VerificRca\Internal\Decodor;
use VerificRca\Internal\FormaInvalida;
use VerificRca\Model\Vehicul;
use VerificRca\Model\VerificareFinala;
use VerificRca\Model\VerificareInCurs;
use VerificRca\Model\WebhookDocumentExpira;
use VerificRca\Model\WebhookRezultat;
use VerificRca\Model\WebhookTest;

final class ModeleTest extends TestCase
{
    private const REZULTAT = [
        'tip' => 'rca',
        'query' => 'B123ABC',
        'numarInmatriculare' => 'B123ABC',
        'serieSasiu' => null,
        'checkedAt' => '2026-09-20T10:00:00.000Z',
        'status' => 'valid',
        'expiresAt' => '2027-03-01T00:00:00.000Z',
    ];

    public function testVerificareFinala(): void
    {
        $r = VerificareFinala::fromArray(self::REZULTAT + ['campNou' => 1, 'hint' => 'trimite si VIN-ul']);
        self::assertSame(TipVerificare::Rca, $r->tip);
        self::assertSame(StatusDocument::Valid, $r->status);
        self::assertSame('2027-03-01T00:00:00+00:00', $r->expiresAt?->format(DATE_ATOM));
        self::assertSame('2026-09-20T10:00:00+00:00', $r->checkedAt->format(DATE_ATOM));
        self::assertNull($r->serieSasiu);
        self::assertNull($r->requestId);
        self::assertSame('trimite si VIN-ul', $r->hint);
        self::assertNull($r->limite);
    }

    /** @return list<array{string, string}> */
    public static function formateData(): array
    {
        return [
            ['2027-03-01T00:00:00.000Z', '2027-03-01T00:00:00+00:00'],
            ['2027-03-01T00:00:00Z', '2027-03-01T00:00:00+00:00'],
            ['2027-03-01T00:00:00.123456Z', '2027-03-01T00:00:00+00:00'],
            ['2027-03-01T03:00:00+03:00', '2027-03-01T03:00:00+03:00'],
            ['2027-03-01', '2027-03-01T00:00:00+00:00'],
        ];
    }

    #[DataProvider('formateData')]
    public function testFormateDeData(string $intrare, string $asteptat): void
    {
        self::assertSame($asteptat, Decodor::data(['d' => $intrare], 'd', '')->format(DATE_ATOM));
    }

    public function testDataInvalidaSpuneCalea(): void
    {
        $this->expectException(FormaInvalida::class);
        $this->expectExceptionMessage('vehicule.2.createdAt');
        Decodor::data(['createdAt' => 'maine'], 'createdAt', 'vehicule.2');
    }

    public function testCampObligatoriuLipsa(): void
    {
        $d = self::REZULTAT;
        unset($d['checkedAt']);
        $this->expectException(FormaInvalida::class);
        $this->expectExceptionMessage('checkedAt: lipseste');
        VerificareFinala::fromArray($d);
    }

    public function testEnumNecunoscut(): void
    {
        $this->expectException(FormaInvalida::class);
        $this->expectExceptionMessage('status');
        VerificareFinala::fromArray(['status' => 'poate'] + self::REZULTAT);
    }

    public function testIntreg(): void
    {
        self::assertSame(14, Decodor::intreg(['p' => 14.0], 'p', ''));
        $this->expectException(FormaInvalida::class);
        Decodor::intreg(['p' => 14.5], 'p', '');
    }

    public function testVehicul(): void
    {
        $v = Vehicul::fromArray([
            'id' => '6f2c1b9e-0000-4000-8000-000000000001',
            'numarInmatriculare' => 'B123ABC',
            'serieSasiu' => null,
            'rcaExpiraLa' => '2027-03-01T00:00:00.000Z',
            'itpExpiraLa' => null,
            'rovinietaExpiraLa' => null,
            'arrExpiraLa' => null,
            'arrMonitorizat' => false,
            'activ' => true,
            'createdAt' => '2026-09-01T08:00:00.000Z',
            'campNouDeLaServer' => 'ignorat',
        ]);
        self::assertSame('B123ABC', $v->numarInmatriculare);
        self::assertNotNull($v->rcaExpiraLa);
        self::assertNull($v->itpExpiraLa);
        self::assertTrue($v->activ);
        self::assertFalse($v->arrMonitorizat);
        self::assertNull($v->serieTalon);
        self::assertFalse($v->serieTalonRespinsa);
    }

    public function testVehiculCuSerieTalonRespinsa(): void
    {
        $v = Vehicul::fromArray([
            'id' => '6f2c1b9e-0000-4000-8000-000000000001',
            'numarInmatriculare' => 'B123ABC',
            'serieSasiu' => 'WVWZZZ1JZXW000001',
            'serieTalon' => 'I00639603F',
            'serieTalonRespinsa' => true,
            'rcaExpiraLa' => null,
            'itpExpiraLa' => null,
            'rovinietaExpiraLa' => null,
            'arrExpiraLa' => null,
            'arrMonitorizat' => false,
            'activ' => true,
            'createdAt' => '2026-09-01T08:00:00.000Z',
        ]);
        self::assertSame('I00639603F', $v->serieTalon);
        self::assertTrue($v->serieTalonRespinsa);
    }

    public function testVerificareInCursSeSerializeazaSiSeReface(): void
    {
        $original = new VerificareInCurs(
            tip: TipVerificare::Rovinieta,
            query: 'B123ABC',
            serieSasiu: 'WVWZZZ1JZXW000001',
            since: 1790000000000,
            requestId: 'req_1',
            queuedAt: new \DateTimeImmutable('2026-09-29T10:00:00.000Z'),
        );
        self::assertSame('processing', $original->status);

        $json = json_encode($original, JSON_THROW_ON_ERROR);
        /** @var array<mixed> $date */
        $date = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $refacut = VerificareInCurs::fromArray($date);

        self::assertSame(TipVerificare::Rovinieta, $refacut->tip);
        self::assertSame('B123ABC', $refacut->query);
        self::assertSame('WVWZZZ1JZXW000001', $refacut->serieSasiu);
        self::assertSame(1790000000000, $refacut->since);
        self::assertSame('req_1', $refacut->requestId);
        self::assertSame('2026-09-29T10:00:00+00:00', $refacut->queuedAt?->format(DATE_ATOM));
    }

    public function testVerificareInCursDinArrayInvalid(): void
    {
        $this->expectException(EroareValidare::class);
        VerificareInCurs::fromArray(['tip' => 'rca', 'query' => 'B123ABC']);
    }

    public function testWebhookRezultat(): void
    {
        $w = WebhookRezultat::fromArray([
            'requestId' => 'req_1',
            'tip' => 'rca',
            'query' => 'B123ABC',
            'serieSasiu' => null,
            'status' => 'valid',
            'result' => self::REZULTAT,
            'checkedAt' => self::REZULTAT['checkedAt'],
        ]);
        self::assertSame(StatusWebhook::Valid, $w->status);
        self::assertNotNull($w->result?->expiresAt);
        self::assertNull($w->error);

        $e = WebhookRezultat::fromArray([
            'requestId' => 'req_2',
            'tip' => 'itp',
            'query' => 'WVWZZZ1JZXW000001',
            'serieSasiu' => 'WVWZZZ1JZXW000001',
            'status' => 'error',
            'result' => null,
            'checkedAt' => null,
            'error' => 'Verificarea nu s-a putut finaliza.',
        ]);
        self::assertSame(StatusWebhook::Error, $e->status);
        self::assertNull($e->result);
        self::assertSame('Verificarea nu s-a putut finaliza.', $e->error);
    }

    public function testWebhookNotificari(): void
    {
        $n = WebhookDocumentExpira::fromArray([
            'event' => 'document.expira',
            'id' => 'liv_1',
            'vehicul' => ['id' => 'v1', 'numarInmatriculare' => 'B123ABC', 'serieSasiu' => null],
            'document' => 'itp',
            'expiraLa' => '2026-10-10T00:00:00.000Z',
            'zileRamase' => 11,
            'prag' => 14,
            'trimisLa' => '2026-09-29T10:00:00.000Z',
        ]);
        self::assertSame('document.expira', $n->event);
        self::assertSame('B123ABC', $n->vehicul->numarInmatriculare);
        self::assertSame(TipVerificare::Itp, $n->document);
        self::assertSame(14, $n->prag);

        $t = WebhookTest::fromArray(['event' => 'webhook.test', 'id' => 't1', 'mesaj' => 'Test', 'trimisLa' => '2026-09-29T10:00:00.000Z']);
        self::assertSame('webhook.test', $t->event);
        self::assertSame('Test', $t->mesaj);
    }

    public function testVehiculWebhookLipsaSpuneCalea(): void
    {
        $this->expectException(FormaInvalida::class);
        $this->expectExceptionMessage('vehicul.id');
        WebhookDocumentExpira::fromArray([
            'event' => 'document.expira',
            'id' => 'liv_1',
            'vehicul' => ['numarInmatriculare' => 'B123ABC', 'serieSasiu' => null],
            'document' => 'itp',
            'expiraLa' => '2026-10-10T00:00:00.000Z',
            'zileRamase' => 11,
            'prag' => 14,
            'trimisLa' => '2026-09-29T10:00:00.000Z',
        ]);
    }
}
