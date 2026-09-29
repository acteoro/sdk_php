<?php

declare(strict_types=1);

namespace VerificRca\E2e;

use PHPUnit\Framework\TestCase;
use VerificRca\Cerere\CerereVerificare;
use VerificRca\Cerere\ReferintaVerificare;
use VerificRca\Cerere\VehiculNou;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareAutentificare;
use VerificRca\Exception\EroareConflict;
use VerificRca\Exception\EroareNegasit;
use VerificRca\Model\VerificareInCurs;
use VerificRca\VerificRca;

// Testele care costa (cota, verificari pe surse) ruleaza doar cu flag explicit.
final class ApiTest extends TestCase
{
    public function testOCheieInexistentaPrimeste401(): void
    {
        try {
            self::client('vrca_e2e_cheie_inexistenta_0000')->vehicule->lista();
            self::fail('trebuia sa arunce EroareAutentificare');
        } catch (EroareAutentificare $e) {
            self::assertSame(401, $e->status);
        }
    }

    public function testListaVehiculelorEsteDecodata(): void
    {
        self::cere('VERIFICRCA_API_KEY');
        $vehicule = self::client()->vehicule->lista();
        foreach ($vehicule as $v) {
            self::assertNotSame('', $v->id);
        }
        self::assertIsList($vehicule);
    }

    public function testAdaugaGasesteSiStergeUnVehicul(): void
    {
        self::cere('VERIFICRCA_API_KEY', 'VERIFICRCA_E2E_NUMAR');
        self::cereFlag('VERIFICRCA_E2E_FLOTA');
        $numar = (string) self::env('VERIFICRCA_E2E_NUMAR');
        $vrca = self::client();

        try {
            $adaugat = $vrca->vehicule->adauga(new VehiculNou($numar, self::env('VERIFICRCA_E2E_VIN')));
        } catch (EroareConflict $e) {
            // Vehiculul e deja in cont: nu il atingem, validam doar raspunsul 409.
            self::assertSame(409, $e->status);

            return;
        }

        $id = $adaugat->id;
        try {
            self::assertSame(strtoupper((string) preg_replace('/[\s-]+/', '', $numar)), $adaugat->numarInmatriculare);
            self::assertContains($id, array_map(static fn($v) => $v->id, $vrca->vehicule->lista()));

            $vrca->vehicule->sterge($id);
            $id = null;
            self::assertNotContains($adaugat->id, array_map(static fn($v) => $v->id, $vrca->vehicule->lista()));

            $this->expectException(EroareNegasit::class);
            $vrca->vehicule->sterge($adaugat->id);
        } finally {
            // Curatenie si cand un test pica la jumatate.
            if ($id !== null) {
                try {
                    $vrca->vehicule->sterge($id);
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRcaPornesteAsteaptaSiRecitesteGratuit(): void
    {
        self::cere('VERIFICRCA_API_KEY', 'VERIFICRCA_E2E_NUMAR');
        self::cereFlag('VERIFICRCA_E2E_VERIFICARI');
        $vrca = self::client();

        $pornit = $vrca->verificari->porneste(new CerereVerificare(TipVerificare::Rca, (string) self::env('VERIFICRCA_E2E_NUMAR'), self::env('VERIFICRCA_E2E_VIN')));
        self::assertGreaterThan(0, $pornit->limite?->zi->limita ?? 0);

        $final = $pornit instanceof VerificareInCurs ? $vrca->verificari->asteapta($pornit) : $pornit;
        self::assertLessThanOrEqual(new \DateTimeImmutable('+1 minute'), $final->checkedAt);

        // GET-ul nu consuma din cota si trebuie sa dea acelasi rezultat.
        $recitit = $vrca->verificari->rezultat(new ReferintaVerificare(TipVerificare::Rca, $final->query));
        self::assertNotInstanceOf(VerificareInCurs::class, $recitit);
        self::assertSame($final->status, $recitit->status);
    }

    public function testItpDupaVinCuVerificaSiAsteapta(): void
    {
        self::cere('VERIFICRCA_API_KEY', 'VERIFICRCA_E2E_VIN');
        self::cereFlag('VERIFICRCA_E2E_VERIFICARI');

        $itp = self::client()->verificari->verificaSiAsteapta(new CerereVerificare(TipVerificare::Itp, (string) self::env('VERIFICRCA_E2E_VIN')));
        self::assertSame(TipVerificare::Itp, $itp->tip);
    }

    private static function client(?string $cheie = null): VerificRca
    {
        return new VerificRca(
            apiKey: $cheie ?? self::env('VERIFICRCA_API_KEY'),
            baseUrl: self::env('VERIFICRCA_BASE_URL') ?? VerificRca::URL_IMPLICIT,
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
