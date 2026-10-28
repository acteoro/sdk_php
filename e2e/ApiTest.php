<?php

declare(strict_types=1);

namespace Acteo\E2e;

use Acteo\Acteo;
use Acteo\Cerere\VehiculNou;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareAutentificare;
use Acteo\Exception\EroareConflict;
use Acteo\Exception\EroareNegasit;
use Acteo\Model\VerificareInCurs;
use PHPUnit\Framework\TestCase;

// Testele care costa (cota, verificari pe surse) ruleaza doar cu flag explicit.
final class ApiTest extends TestCase
{
    public function testOCheieInexistentaPrimeste401(): void
    {
        try {
            self::client('e2e_cheie_inexistenta_00000')->auto->flota->lista();
            self::fail('trebuia sa arunce EroareAutentificare');
        } catch (EroareAutentificare $e) {
            self::assertSame(401, $e->status);
        }
    }

    public function testListaVehiculelorEsteDecodata(): void
    {
        self::cere('ACTEO_API_KEY');
        $vehicule = self::client()->auto->flota->lista();
        foreach ($vehicule as $v) {
            self::assertNotSame('', $v->id);
        }
        self::assertIsList($vehicule);
    }

    public function testAdaugaGasesteSiStergeUnVehicul(): void
    {
        self::cere('ACTEO_API_KEY', 'ACTEO_E2E_NUMAR');
        self::cereFlag('ACTEO_E2E_FLOTA');
        $numar = (string) self::env('ACTEO_E2E_NUMAR');
        $acteo = self::client();

        try {
            $adaugat = $acteo->auto->flota->adauga(new VehiculNou($numar, self::env('ACTEO_E2E_VIN')));
        } catch (EroareConflict $e) {
            // Vehiculul e deja in cont: nu il atingem, validam doar raspunsul 409.
            self::assertSame(409, $e->status);

            return;
        }

        $id = $adaugat->id;
        try {
            self::assertSame(strtoupper((string) preg_replace('/[\s-]+/', '', $numar)), $adaugat->numarInmatriculare);
            self::assertContains($id, array_map(static fn($v) => $v->id, $acteo->auto->flota->lista()));

            $acteo->auto->flota->sterge($id);
            $id = null;
            self::assertNotContains($adaugat->id, array_map(static fn($v) => $v->id, $acteo->auto->flota->lista()));

            $this->expectException(EroareNegasit::class);
            $acteo->auto->flota->sterge($adaugat->id);
        } finally {
            // Curatenie si cand un test pica la jumatate.
            if ($id !== null) {
                try {
                    $acteo->auto->flota->sterge($id);
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRcaPornesteAsteaptaSiRecitesteGratuit(): void
    {
        self::cere('ACTEO_API_KEY', 'ACTEO_E2E_NUMAR');
        self::cereFlag('ACTEO_E2E_VERIFICARI');
        $acteo = self::client();
        $numar = (string) self::env('ACTEO_E2E_NUMAR');
        $vin = self::env('ACTEO_E2E_VIN');

        $pornit = $acteo->auto->rca->porneste($vin !== null ? ['numar' => $numar, 'vin' => $vin] : $numar);
        self::assertGreaterThan(0, $pornit->limite?->zi->limita ?? 0);

        $final = $pornit instanceof VerificareInCurs ? $acteo->auto->rca->asteapta($pornit) : $pornit;
        self::assertLessThanOrEqual(new \DateTimeImmutable('+1 minute'), $final->checkedAt);

        // GET-ul nu consuma din cota si trebuie sa dea acelasi rezultat.
        $recitit = $acteo->auto->rca->rezultat($final->query);
        self::assertNotInstanceOf(VerificareInCurs::class, $recitit);
        self::assertSame($final->status, $recitit->status);
    }

    public function testItpDupaVinCuVerifica(): void
    {
        self::cere('ACTEO_API_KEY', 'ACTEO_E2E_VIN');
        self::cereFlag('ACTEO_E2E_VERIFICARI');

        $itp = self::client()->auto->itp->verifica((string) self::env('ACTEO_E2E_VIN'));
        self::assertSame(TipVerificare::Itp, $itp->tip);
    }

    private static function client(?string $cheie = null): Acteo
    {
        return new Acteo(
            apiKey: $cheie ?? self::env('ACTEO_API_KEY'),
            baseUrl: self::env('ACTEO_BASE_URL') ?? Acteo::URL_IMPLICIT,
            userAgent: 'e2e',
        );
    }

    private static function env(string $nume): ?string
    {
        $valoare = getenv($nume);

        return is_string($valoare) && trim($valoare) !== '' ? trim($valoare) : null;
    }

    private static function cere(string ...$nume): void
    {
        foreach ($nume as $n) {
            if (self::env($n) === null) {
                self::markTestSkipped("lipseste {$n} in .env.e2e");
            }
        }
    }

    private static function cereFlag(string $nume): void
    {
        if (self::env($nume) !== '1') {
            self::markTestSkipped("{$nume}=1 nu e setat");
        }
    }
}
