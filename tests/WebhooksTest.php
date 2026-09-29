<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use VerificRca\Enum\StatusWebhook;
use VerificRca\Exception\EroareWebhook;
use VerificRca\Model\WebhookDocumentExpira;
use VerificRca\Model\WebhookTest;
use VerificRca\Tests\Ajutor\Ajutor;
use VerificRca\Webhooks;

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
        $n = Webhooks::verificaWebhookNotificare(json_encode(self::EXPIRA, JSON_THROW_ON_ERROR), ['X-Verificrca-Key' => self::CHEIE], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
        self::assertSame(14, $n->prag);
        self::assertSame('2026-10-10', $n->expiraLa->format('Y-m-d'));
    }

    public function testAcceptaHeadereleInFormaLaravel(): void
    {
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-verificrca-key' => [self::CHEIE]], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaHeadereleDinServer(): void
    {
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, ['HTTP_X_VERIFICRCA_KEY' => self::CHEIE, 'REQUEST_METHOD' => 'POST'], self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaCererePsr7(): void
    {
        $cerere = new ServerRequest('POST', 'https://example.com/hook', ['X-Verificrca-Key' => self::CHEIE]);
        $n = Webhooks::verificaWebhookNotificare(self::EXPIRA, $cerere, self::CHEIE);
        self::assertInstanceOf(WebhookDocumentExpira::class, $n);
    }

    public function testAcceptaEvenimentulDeTest(): void
    {
        $n = Webhooks::verificaWebhookNotificare(
            ['event' => 'webhook.test', 'id' => 't1', 'mesaj' => 'Test', 'trimisLa' => '2026-09-29T10:00:00.000Z'],
            ['X-Verificrca-Key' => 'k'],
            'k',
        );
        self::assertInstanceOf(WebhookTest::class, $n);
    }

    public function testRespingeCheiaGresitaSauLipsa(): void
    {
        self::assertSame('CHEIE_INVALIDA', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-verificrca-key' => 'gresit'], self::CHEIE))->cod);

        $lipsa = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, [], self::CHEIE));
        self::assertSame('CHEIE_INVALIDA', $lipsa->cod);
        self::assertStringContainsString('X-Verificrca-Key', $lipsa->getMessage());

        self::assertSame('CHEIE_NECONFIGURATA', $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(self::EXPIRA, ['x-verificrca-key' => ''], ''))->cod);
    }

    public function testEvenimentNecunoscut(): void
    {
        $e = $this->eroare(static fn() => Webhooks::verificaWebhookNotificare(['event' => 'altceva', 'id' => 'x'], ['x-verificrca-key' => 'k'], 'k'));
        self::assertSame('PAYLOAD_INVALID', $e->cod);
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
