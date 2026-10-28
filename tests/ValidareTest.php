<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\VehiculNou;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Validare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidareTest extends TestCase
{
    private const VIN = 'WVWZZZ1JZXW000001';

    public function testNormalizeaza(): void
    {
        self::assertSame('B123ABC', Validare::normalizeaza(' b-123 abc '));
        self::assertSame('B123ABC', Validare::normalizeaza("b_123\tabc"));
    }

    /** @return list<array{string}> */
    public static function numereValide(): array
    {
        return [['B123ABC'], ['B12ABC'], ['CJ45XYZ'], ['IS08BCD'], ['CD123A'], ['TC1234AB'], ['b 123 abc']];
    }

    #[DataProvider('numereValide')]
    public function testAcceptaNumarul(string $numar): void
    {
        self::assertTrue(Validare::esteNumarInmatriculareValid($numar));
    }

    /** @return list<array{string}> */
    public static function numereInvalide(): array
    {
        return [['XX12ABC'], ['B1ABC'], ['123'], ['']];
    }

    #[DataProvider('numereInvalide')]
    public function testRespingeNumarul(string $numar): void
    {
        self::assertFalse(Validare::esteNumarInmatriculareValid($numar));
    }

    public function testVin(): void
    {
        self::assertTrue(Validare::esteVinValid(self::VIN));
        self::assertTrue(Validare::esteVinValid('wvwzzz1jzxw000001'));
        self::assertFalse(Validare::esteVinValid('WVWZZZ1JZXW00000I'));
        self::assertFalse(Validare::esteVinValid('WVWZZZ1JZXW0000'));
    }

    public function testRcaPeNumarFaraVin(): void
    {
        $t = Validare::valideazaCerereVerificare(new CerereVerificare(TipVerificare::Rca, 'b 123 abc'));
        self::assertSame(TipVerificare::Rca, $t->tip);
        self::assertSame('B123ABC', $t->query);
        self::assertNull($t->serieSasiu);
        self::assertSame('B123ABC', $t->queryPolling);
        self::assertNull($t->callbackUrl);
    }

    public function testRcaPeNumarCuVinFacePollingPeVin(): void
    {
        $t = Validare::valideazaCerereVerificare(new CerereVerificare('rca', 'B123ABC', strtolower(self::VIN)));
        self::assertSame('B123ABC', $t->query);
        self::assertSame(self::VIN, $t->serieSasiu);
        self::assertSame(self::VIN, $t->queryPolling);
    }

    public function testTipCaStringCuMajusculeSiSpatii(): void
    {
        self::assertSame(TipVerificare::Rca, Validare::valideazaCerereVerificare(new CerereVerificare('RCA', 'B123ABC'))->tip);
        self::assertSame(TipVerificare::Itp, Validare::valideazaCerereVerificare(new CerereVerificare(' itp ', self::VIN))->tip);
    }

    public function testItpPeNumarFaraVinERespins(): void
    {
        $e = $this->eroare(new CerereVerificare(TipVerificare::Itp, 'B123ABC'));
        self::assertStringContainsString('RAR', $e->getMessage());
        self::assertSame('VALIDARE_LOCALA', $e->cod);
        self::assertSame('query', $e->camp);
    }

    public function testItpCuVinInSerieSasiuFacePollingPeVin(): void
    {
        $t = Validare::valideazaCerereVerificare(new CerereVerificare(TipVerificare::Itp, 'B123ABC', self::VIN));
        self::assertSame(self::VIN, $t->queryPolling);
        self::assertSame(self::VIN, $t->serieSasiu);
    }

    public function testRovinietaCereSiVin(): void
    {
        self::assertSame('serieSasiu', $this->eroare(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', serieTalon: 'I00639603F'))->camp);
        $t = Validare::valideazaCerereVerificare(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', self::VIN, serieTalon: 'I00639603F'));
        self::assertSame('B123ABC', $t->queryPolling);
        self::assertSame(self::VIN, $t->serieSasiu);
        self::assertSame('I00639603F', $t->serieTalon);
    }

    public function testRovinietaCereSerieTalon(): void
    {
        $e = $this->eroare(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', self::VIN));
        self::assertSame('serieTalon', $e->camp);
        self::assertStringContainsString('certificatului de inmatriculare', $e->getMessage());
        self::assertSame('serieTalon', $this->eroare(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', self::VIN, serieTalon: ' - '))->camp);
    }

    public function testRovinietaNormalizeazaSerieTalon(): void
    {
        $t = Validare::valideazaCerereVerificare(new CerereVerificare('rovinieta', 'B123ABC', self::VIN, serieTalon: ' i 00639603-f '));
        self::assertSame('I00639603F', $t->serieTalon);
    }

    /** @return iterable<string, array{string}> */
    public static function seriiTalonInvalide(): iterable
    {
        yield 'prea scurta' => ['I0063'];
        yield 'prea lunga' => [str_repeat('A1', 11)];
        yield 'fara cifre' => ['ABCDEFGH'];
    }

    #[DataProvider('seriiTalonInvalide')]
    public function testRovinietaRespingeSerieTalon(string $serie): void
    {
        $e = $this->eroare(new CerereVerificare(TipVerificare::Rovinieta, 'B123ABC', self::VIN, serieTalon: $serie));
        self::assertSame('serieTalon', $e->camp);
        self::assertFalse(Validare::esteSerieTalonValida($serie));
    }

    public function testSerieTalonIgnorataLaCelelalteVerificari(): void
    {
        self::assertNull(Validare::valideazaCerereVerificare(new CerereVerificare('rca', 'B123ABC', serieTalon: 'x'))->serieTalon);
    }

    public function testEsteSerieTalonValida(): void
    {
        self::assertTrue(Validare::esteSerieTalonValida('I00639603F'));
        self::assertTrue(Validare::esteSerieTalonValida('i 006-39603f'));
        self::assertSame('I00639603F', Validare::normalizeazaSerieTalon(' i 00639603-f '));
    }

    public function testArrNuAcceptaVin(): void
    {
        self::assertSame('query', $this->eroare(new CerereVerificare(TipVerificare::Arr, self::VIN))->camp);
    }

    public function testExplicaLungimeaGresitaAVinului(): void
    {
        $e = $this->eroare(new CerereVerificare(TipVerificare::Itp, 'WVWZZZ1JZXW0000'));
        self::assertStringContainsString('15 caractere', $e->getMessage());
    }

    public function testTipNecunoscutIntoarceValorileAcceptate(): void
    {
        $e = $this->eroare(new CerereVerificare('casco', 'B123ABC'));
        self::assertSame(['rca', 'itp', 'rovinieta', 'arr'], $e->acceptate);
        self::assertSame('tip', $e->camp);
    }

    public function testQueryGol(): void
    {
        self::assertSame('query', $this->eroare(new CerereVerificare(TipVerificare::Rca, '  '))->camp);
    }

    public function testCallbackUrl(): void
    {
        self::assertStringContainsString('http sau https', $this->eroare(new CerereVerificare('rca', 'B123ABC', null, 'ftp://x.ro'))->getMessage());
        self::assertStringContainsString('absolut', $this->eroare(new CerereVerificare('rca', 'B123ABC', null, '/relativ'))->getMessage());
        self::assertSame('callbackUrl', $this->eroare(new CerereVerificare('rca', 'B123ABC', null, 'https://example.com/' . str_repeat('a', 2000)))->camp);

        $t = Validare::valideazaCerereVerificare(new CerereVerificare('rca', 'B123ABC', null, ' https://example.com/hook/secret '));
        self::assertSame('https://example.com/hook/secret', $t->callbackUrl);
        self::assertNull(Validare::valideazaCerereVerificare(new CerereVerificare('rca', 'B123ABC', null, '  '))->callbackUrl);
    }

    public function testIdentificatoriiSuntValidatiInainteaCallbackUrl(): void
    {
        self::assertSame('query', $this->eroare(new CerereVerificare('itp', 'B123ABC', null, 'ftp://x.ro'))->camp);
    }

    public function testVehiculNouCereUnIdentificator(): void
    {
        $this->expectException(EroareValidare::class);
        Validare::valideazaVehiculNou(new VehiculNou());
    }

    public function testVehiculNouNormalizeaza(): void
    {
        self::assertSame(
            ['numarInmatriculare' => 'B123ABC', 'serieSasiu' => 'ABC'],
            Validare::valideazaVehiculNou(new VehiculNou('b-123 abc', 'abc')),
        );
        self::assertSame(
            ['serieSasiu' => self::VIN, 'arrMonitorizat' => false],
            Validare::valideazaVehiculNou(new VehiculNou(null, self::VIN, false)),
        );
    }

    public function testVehiculNouCuSerieTalon(): void
    {
        self::assertSame(
            ['numarInmatriculare' => 'B123ABC', 'serieSasiu' => self::VIN, 'serieTalon' => 'I00639603F'],
            Validare::valideazaVehiculNou(new VehiculNou('B123ABC', self::VIN, serieTalon: 'i00639603f')),
        );
        self::assertArrayNotHasKey('serieTalon', Validare::valideazaVehiculNou(new VehiculNou('B123ABC', serieTalon: '  ')));
        try {
            Validare::valideazaVehiculNou(new VehiculNou('B123ABC', serieTalon: 'ABCDEFGH'));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('serieTalon', $e->camp);
        }
    }

    public function testVehiculNouRespingeCaractere(): void
    {
        try {
            Validare::valideazaVehiculNou(new VehiculNou('B 123/ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('numarInmatriculare', $e->camp);
        }
        try {
            Validare::valideazaVehiculNou(new VehiculNou(null, 'WVW-ZZZ'));
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('serieSasiu', $e->camp);
        }
    }

    private function eroare(CerereVerificare $c): EroareValidare
    {
        try {
            Validare::valideazaCerereVerificare($c);
        } catch (EroareValidare $e) {
            return $e;
        }
        self::fail('trebuia sa arunce EroareValidare');
    }
}
