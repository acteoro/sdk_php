<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use PHPUnit\Framework\TestCase;
use VerificRca\Enum\StatusWebhook;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareConfigurare;
use VerificRca\Exception\EroareLimita;
use VerificRca\Exception\EroareRetea;
use VerificRca\Exception\EroareValidare;
use VerificRca\Exception\EroareWebhook;
use VerificRca\Exception\VerificRcaException;

final class ExceptiiTest extends TestCase
{
    public function testCampurileComune(): void
    {
        $cauza = new \RuntimeException('jos');
        $e = new EroareConfigurare('mesaj', status: 400, cod: 'X', hint: 'fa asa', raspuns: ['a' => 1], previous: $cauza);

        self::assertInstanceOf(VerificRcaException::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('mesaj', $e->getMessage());
        self::assertSame(400, $e->status);
        self::assertSame('X', $e->cod);
        self::assertSame('fa asa', $e->hint);
        self::assertSame(['a' => 1], $e->raspuns);
        self::assertSame($cauza, $e->getPrevious());
    }

    public function testCampurileSpecifice(): void
    {
        $v = new EroareValidare('m', cod: 'VALIDARE_LOCALA', acceptate: ['rca'], camp: 'tip');
        self::assertSame(['rca'], $v->acceptate);
        self::assertSame('tip', $v->camp);

        $l = new EroareLimita('m', status: 429, retryAfterMs: 2000, utilizareZi: '300/300', utilizareLuna: '1/2');
        self::assertSame(2000, $l->retryAfterMs);
        self::assertSame('300/300', $l->utilizareZi);
        self::assertSame('1/2', $l->utilizareLuna);

        $r = new EroareRetea('m', timeout: true);
        self::assertTrue($r->timeout);
        self::assertFalse($r->neajunsa);

        self::assertNull((new EroareWebhook('m'))->status);
    }

    public function testEnumuri(): void
    {
        self::assertSame(TipVerificare::Rovinieta, TipVerificare::from('rovinieta'));
        self::assertSame('not_found', StatusWebhook::NotFound->value);
        self::assertSame('error', StatusWebhook::Error->value);
    }
}
