<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Acteo;
use Acteo\Cerere\CerereImagine;
use Acteo\Enum\CifraControl;
use Acteo\Enum\StatusImagine;
use Acteo\Enum\SursaAn;
use Acteo\Enum\UnghiImagine;
use Acteo\Exception\EroareLimita;
use Acteo\Exception\EroareServer;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Tests\Ajutor\CeasFals;
use Acteo\Tests\Ajutor\ClientFals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProduseTest extends TestCase
{
    private const COTA = [
        'X-RateLimit-Limit-Day' => '100',
        'X-RateLimit-Remaining-Day' => '99',
        'X-RateLimit-Limit-Month' => '1000',
        'X-RateLimit-Remaining-Month' => '999',
    ];

    private const DECODARE = [
        'vin' => 'WVWZZZ1KZBW000000',
        'gasit' => true,
        'taxat' => true,
        'marca' => 'Volkswagen',
        'model' => 'Golf',
        'anFabricatie' => 2011,
        'sursaAn' => 'pozitia-10',
        'modelePosibile' => [],
        'structura' => [
            'wmi' => 'WVW',
            'vds' => 'ZZZ1KZ',
            'vis' => 'BW000000',
            'regiune' => 'Europa',
            'tara' => 'Germania',
            'cifraControl' => 'neaplicabila',
            'cifraControlAsteptata' => null,
            'aniPosibili' => [2011],
        ],
    ];

    private const IMAGINE_GATA = [
        'gata' => true,
        'status' => 'gata',
        'taxat' => true,
        'url' => 'https://example.com/golf.webp',
        'expiraLa' => '2026-11-01T10:00:00.000Z',
        'unghi' => 'trei-sferturi',
        'umbra' => true,
        'latime' => 1600,
        'inaltime' => 900,
        'marca' => 'Volkswagen',
        'model' => 'Golf',
        'generatie' => ['eticheta' => 'Volkswagen Golf VII', 'anDe' => 2012, 'anPana' => 2019, 'caroserie' => '5-door hatchback'],
    ];

    private const IMAGINE_IN_LUCRU = ['gata' => false, 'status' => 'in-lucru', 'taxat' => false, 'generatie' => null] + self::IMAGINE_GATA;

    private static function acteo(ClientFals $client, ?CeasFals $ceas = null): Acteo
    {
        return Ajutor::acteo($client, $ceas, ['incercari' => 3, 'intarziereInitialaMs' => 1, 'intarziereMaximaMs' => 20000]);
    }

    // Decodare VIN

    public function testDecodeazaTrimiteVinulNormalizatSiIntoarceDecodareaCuCota(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::DECODARE, self::COTA));
        $d = self::acteo($client)->auto->vin->decodeaza(' wvwzzz1kzbw000000 ');

        $c = $client->cerere();
        self::assertSame('POST', $c->getMethod());
        self::assertSame('/api/public/v1/decodare-vin', $c->getUri()->getPath());
        self::assertSame(['vin' => 'WVWZZZ1KZBW000000'], $client->corp());
        self::assertSame('Volkswagen', $d->marca);
        self::assertSame(SursaAn::Pozitia10, $d->sursaAn);
        self::assertSame([2011], $d->structura->aniPosibili);
        self::assertSame(CifraControl::Neaplicabila, $d->structura->cifraControl);
        self::assertNotNull($d->limite);
        self::assertSame(100, $d->limite->zi->limita);
        self::assertSame(99, $d->limite->zi->ramase);
        self::assertSame(1000, $d->limite->luna->limita);
        self::assertSame(999, $d->limite->luna->ramase);
    }

    public function testDecodeazaOpresteLocalUnVinInvalid(): void
    {
        $client = new ClientFals();
        try {
            self::acteo($client)->auto->vin->decodeaza('WVWZZZ1KZBW00000O');
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('vin', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testPlafonNetaxatDevineEroareLimitaFaraReincercare(): void
    {
        $client = new ClientFals(ClientFals::raspuns(429, ['error' => 'Plafon', 'code' => 'PLAFON_NETAXAT']));
        try {
            self::acteo($client)->auto->vin->decodeaza(self::DECODARE['vin']);
            self::fail('trebuia sa arunce');
        } catch (EroareLimita $e) {
            self::assertSame('PLAFON_NETAXAT', $e->cod);
        }
        self::assertCount(1, $client->cereri);
    }

    // Numere disponibile

    public function testVerificaNumarulTrimiteNumarulNormalizat(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['numar' => 'BV07ABC', 'disponibil' => true, 'mesaj' => null], self::COTA));
        $r = self::acteo($client)->auto->numere->verifica('bv 07 abc');

        self::assertSame('/api/public/v1/numere-disponibile', $client->cerere()->getUri()->getPath());
        self::assertSame(['numar' => 'BV07ABC'], $client->corp());
        self::assertTrue($r->disponibil);
        self::assertSame(99, $r->limite?->zi->ramase);
    }

    public function testNumereIncompleteSauCuWildcardSuntOpriteLocal(): void
    {
        $client = new ClientFals();
        $a = self::acteo($client);
        foreach (['BV07AB', 'BV07A?C', 'BV07A*C', 'XX07ABC', '', 'CD123A'] as $numar) {
            try {
                $a->auto->numere->verifica($numar);
                self::fail("trebuia sa arunce pentru {$numar}");
            } catch (EroareValidare $e) {
                self::assertSame('numar', $e->camp);
                if (str_contains($numar, '?')) {
                    self::assertStringContainsString('`?`', $e->getMessage());
                }
            }
        }
        self::assertCount(0, $client->cereri);

        $ok = new ClientFals(ClientFals::raspuns(200, ['numar' => 'B123ABC', 'disponibil' => false, 'mesaj' => 'Inregistrat']));
        self::assertFalse(self::acteo($ok)->auto->numere->verifica('B 123 ABC')->disponibil);
        self::assertSame(['numar' => 'B123ABC'], $ok->corp());
    }

    public function testNumarRespins422EsteEroareValidare(): void
    {
        $client = new ClientFals(ClientFals::raspuns(422, ['error' => 'Respins', 'code' => 'NUMAR_RESPINS']));
        try {
            self::acteo($client)->auto->numere->verifica('BV07ABC');
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('NUMAR_RESPINS', $e->cod);
            self::assertSame(422, $e->status);
        }
    }

    public function testReincearcaOcupatDupaRetryAfter(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(503, ['error' => 'Ocupat', 'code' => 'OCUPAT'], ['Retry-After' => '10']),
            ClientFals::raspuns(200, ['numar' => 'BV07ABC', 'disponibil' => true, 'mesaj' => null]),
        );
        self::assertTrue(self::acteo($client, $ceas)->auto->numere->verifica('BV07ABC')->disponibil);
        self::assertCount(2, $client->cereri);
        self::assertCount(1, $ceas->somnuri);
        self::assertGreaterThanOrEqual(10000, $ceas->somnuri[0]);
    }

    public function testOcupatCuRetryAfterPestePauzaMaximaNuSeReincearca(): void
    {
        $client = new ClientFals(ClientFals::raspuns(503, ['error' => 'Ocupat', 'code' => 'OCUPAT'], ['Retry-After' => '60']));
        try {
            self::acteo($client)->auto->numere->verifica('BV07ABC');
            self::fail('trebuia sa arunce');
        } catch (EroareServer $e) {
            self::assertSame(60000, $e->retryAfterMs);
        }
        self::assertCount(1, $client->cereri);
    }

    /** @return list<array{int, string}> */
    public static function eroriNerepetabile(): array
    {
        return [[504, 'TIMEOUT'], [502, 'EROARE_DGPCI'], [503, 'INDISPONIBIL']];
    }

    #[DataProvider('eroriNerepetabile')]
    public function testNuRepetaEroareaCuCodDeLaApi(int $status, string $cod): void
    {
        $client = new ClientFals(ClientFals::raspuns($status, ['error' => 'x', 'code' => $cod]));
        try {
            self::acteo($client)->auto->numere->verifica('BV07ABC');
            self::fail('trebuia sa arunce');
        } catch (EroareServer $e) {
            self::assertSame($cod, $e->cod);
            self::assertSame($status, $e->status);
        }
        self::assertCount(1, $client->cereri);
    }

    // Imagini masini

    public function testObtineTrimiteDoarCampurileDateSiDecodeazaPoza(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::IMAGINE_GATA, self::COTA));
        $p = self::acteo($client)->auto->imagini->obtine(['marca' => ' Volkswagen ', 'model' => 'Golf', 'an' => 2015, 'unghi' => 'fata']);

        self::assertSame('/api/public/v1/imagini-masini', $client->cerere()->getUri()->getPath());
        self::assertSame(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015, 'unghi' => 'fata'], $client->corp());
        self::assertSame(StatusImagine::Gata, $p->status);
        self::assertSame('2026-11-01T10:00:00+00:00', $p->expiraLa?->format(DATE_ATOM));
        self::assertSame(2019, $p->generatie?->anPana);
        self::assertSame(UnghiImagine::TreiSferturi, $p->unghi);
        self::assertSame(99, $p->limite?->zi->ramase);
        self::assertNull($p->retryAfterMs);
    }

    public function testObtineAcceptaCerereImagineCuEnum(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::IMAGINE_GATA));
        self::acteo($client)->auto->imagini->obtine(new CerereImagine('Volkswagen', 'Golf', 2015, UnghiImagine::ProfilDreapta, false));
        self::assertSame(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015, 'unghi' => 'profil-dreapta', 'umbra' => false], $client->corp());
    }

    public function testLaInLucruExpuneRetryAfterMs(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU, ['Retry-After' => '15']));
        $p = self::acteo($client)->auto->imagini->obtine(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015]);
        self::assertSame(StatusImagine::InLucru, $p->status);
        self::assertSame(15000, $p->retryAfterMs);
        self::assertNull($p->generatie);
    }

    public function testValideazaLocalMarcaModelulAnulSiUnghiul(): void
    {
        $client = new ClientFals();
        $a = self::acteo($client);
        $cazuri = [
            [['marca' => '', 'model' => 'Golf', 'an' => 2015], 'marca', '/marca/'],
            [['marca' => 'VW', 'model' => 'Golf<script>', 'an' => 2015], 'model', '/model/'],
            [['marca' => str_repeat('a', 67), 'model' => 'Golf', 'an' => 2015], 'marca', '/66/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => 1900], 'an', '/1950/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => (int) date('Y') + 2], 'an', '/1950/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => '2015'], 'an', '/1950/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => 2015, 'unghi' => 'sus'], 'unghi', '/unghi/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => 2015, 'umbra' => 'da'], 'umbra', '/umbra/'],
            [['marca' => 'VW', 'model' => 'Golf', 'an' => 2015, 'culoare' => 'rosu'], 'identificator', '/culoare/'],
        ];
        foreach ($cazuri as [$cerere, $camp, $mesaj]) {
            try {
                // @phpstan-ignore argument.type (testam tocmai valorile de alt tip)
                $a->auto->imagini->obtine($cerere);
                self::fail('trebuia sa arunce pentru ' . json_encode($cerere));
            } catch (EroareValidare $e) {
                self::assertSame($camp, $e->camp);
                self::assertMatchesRegularExpression($mesaj, $e->getMessage());
            }
        }
        self::assertCount(0, $client->cereri);

        $ok = new ClientFals(ClientFals::raspuns(200, self::IMAGINE_GATA));
        self::acteo($ok)->auto->imagini->obtine(['marca' => 'Škoda', 'model' => "Octavia (A7) 1.6 TDI d'or & co./+", 'an' => 1950]);
        self::assertSame('Škoda', $ok->corp()['marca']);
    }

    public function testAsteaptaCereDinNouDupaRetryAfterPanaNuMaiEInLucru(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU, ['Retry-After' => '20']),
            ClientFals::raspuns(200, self::IMAGINE_GATA),
        );
        $p = self::acteo($client, $ceas)->auto->imagini->asteapta(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015]);
        self::assertSame(StatusImagine::Gata, $p->status);
        self::assertSame([20000], $ceas->somnuri);
        self::assertCount(2, $client->cereri);
    }

    public function testAsteaptaFaraRetryAfterFacePauzaDeCelPutin10Secunde(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU, ['Retry-After' => '1']),
            ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU),
            ClientFals::raspuns(200, ['status' => 'necunoscut', 'gata' => false, 'taxat' => false] + self::IMAGINE_GATA),
        );
        $p = self::acteo($client, $ceas)->auto->imagini->asteapta(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015]);
        self::assertSame(StatusImagine::Necunoscut, $p->status);
        self::assertSame([10000, 10000], $ceas->somnuri);
    }

    public function testAsteaptaRenuntaDupaTimeoutMs(): void
    {
        $ceas = new CeasFals();
        $client = new ClientFals(
            ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU, ['Retry-After' => '10']),
            ClientFals::raspuns(200, self::IMAGINE_IN_LUCRU, ['Retry-After' => '10']),
        );
        try {
            self::acteo($client, $ceas)->auto->imagini->asteapta(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015], 15000);
            self::fail('trebuia sa arunce');
        } catch (EroareTimeoutVerificare $e) {
            self::assertSame('TIMEOUT_IMAGINE', $e->cod);
        }
        self::assertCount(2, $client->cereri);
    }
}
