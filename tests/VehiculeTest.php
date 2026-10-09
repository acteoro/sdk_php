<?php

declare(strict_types=1);

namespace VerificRca\Tests;

use PHPUnit\Framework\TestCase;
use VerificRca\Cerere\VehiculNou;
use VerificRca\Exception\EroareConflict;
use VerificRca\Exception\EroareInterzis;
use VerificRca\Exception\EroareNegasit;
use VerificRca\Exception\EroareRaspunsInvalid;
use VerificRca\Exception\EroareServer;
use VerificRca\Exception\EroareValidare;
use VerificRca\Tests\Ajutor\Ajutor;
use VerificRca\Tests\Ajutor\ClientFals;

final class VehiculeTest extends TestCase
{
    private const VEHICUL = [
        'id' => '6f2c1b9e-0000-4000-8000-000000000001',
        'numarInmatriculare' => 'B123ABC',
        'serieSasiu' => Ajutor::VIN,
        'rcaExpiraLa' => '2027-03-01T00:00:00.000Z',
        'itpExpiraLa' => null,
        'rovinietaExpiraLa' => '2026-12-31T00:00:00.000Z',
        'arrExpiraLa' => null,
        'arrMonitorizat' => false,
        'activ' => true,
        'createdAt' => '2026-09-01T08:00:00.000Z',
        'campNouDeLaServer' => 'ignorat',
    ];

    public function testListaDecodeazaDatele(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => [self::VEHICUL]]));
        $vehicule = Ajutor::client($client)->vehicule->lista();

        self::assertCount(1, $vehicule);
        self::assertSame('2027-03-01', $vehicule[0]->rcaExpiraLa?->format('Y-m-d'));
        self::assertNull($vehicule[0]->itpExpiraLa);
        self::assertTrue($vehicule[0]->activ);
        self::assertSame('GET', $client->cerere()->getMethod());
        self::assertSame('/api/public/v1/vehicule', $client->cerere()->getUri()->getPath());
    }

    public function testListaGoala(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => []]));
        self::assertSame([], Ajutor::client($client)->vehicule->lista());
    }

    public function testListaCuVehiculInvalidSpuneIndexul(): void
    {
        $rau = self::VEHICUL;
        unset($rau['activ']);
        $client = new ClientFals(ClientFals::raspuns(200, ['vehicule' => [self::VEHICUL, $rau]]));
        $this->expectException(EroareRaspunsInvalid::class);
        $this->expectExceptionMessage('vehicule.1.activ');
        Ajutor::client($client)->vehicule->lista();
    }

    public function testAdaugaTrimiteFormaCanonica(): void
    {
        $client = new ClientFals(ClientFals::raspuns(201, ['vehicul' => self::VEHICUL]));
        $v = Ajutor::client($client)->vehicule->adauga(new VehiculNou('b 123-abc', null, false));

        self::assertSame(self::VEHICUL['id'], $v->id);
        self::assertSame('POST', $client->cerere()->getMethod());
        self::assertSame(['numarInmatriculare' => 'B123ABC', 'arrMonitorizat' => false], $client->corp());
    }

    public function testAdaugaTrimiteSerieTalon(): void
    {
        $client = new ClientFals(ClientFals::raspuns(201, ['vehicul' => self::VEHICUL + ['serieTalon' => 'I00639603F', 'serieTalonRespinsa' => false]]));
        $v = Ajutor::client($client)->vehicule->adauga(new VehiculNou('B123ABC', Ajutor::VIN, serieTalon: 'i00639603f'));

        self::assertSame(['numarInmatriculare' => 'B123ABC', 'serieSasiu' => Ajutor::VIN, 'serieTalon' => 'I00639603F'], $client->corp());
        self::assertSame('I00639603F', $v->serieTalon);
        self::assertFalse($v->serieTalonRespinsa);
    }

    public function testAdaugaValideazaLocal(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::client($client)->vehicule->adauga(new VehiculNou());
            self::fail('trebuia sa arunce');
        } catch (EroareValidare) {
        }
        self::assertCount(0, $client->cereri);
    }

    public function testConflictSiLimitaDePlan(): void
    {
        $c1 = new ClientFals(ClientFals::raspuns(409, ['error' => 'Exista deja.', 'code' => 'VEHICUL_EXISTENT']));
        try {
            Ajutor::client($c1)->vehicule->adauga(new VehiculNou('B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareConflict $e) {
            self::assertSame('VEHICUL_EXISTENT', $e->cod);
        }

        $c2 = new ClientFals(ClientFals::raspuns(403, ['error' => 'Limita.', 'code' => 'LIMIT_ATINS', 'limita' => 5]));
        try {
            Ajutor::client($c2)->vehicule->adauga(new VehiculNou('B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareInterzis $e) {
            self::assertSame('LIMIT_ATINS', $e->cod);
            self::assertSame(5, $e->limita);
        }
    }

    public function testAdaugaNuSeRepetaLa500(): void
    {
        $client = new ClientFals(ClientFals::raspuns(500));
        try {
            Ajutor::client($client)->vehicule->adauga(new VehiculNou('B123ABC'));
            self::fail('trebuia sa arunce');
        } catch (EroareServer) {
        }
        self::assertCount(1, $client->cereri);
    }

    public function testStergeEncodeazaIdulSiMapeaza404(): void
    {
        $c1 = new ClientFals(ClientFals::raspuns(200, ['success' => true]));
        Ajutor::client($c1)->vehicule->sterge('abc/def');
        self::assertSame('DELETE', $c1->cerere()->getMethod());
        self::assertSame('/api/public/v1/vehicule/abc%2Fdef', $c1->cerere()->getUri()->getPath());

        $c2 = new ClientFals(ClientFals::raspuns(404, ['error' => 'Vehicul negasit.', 'code' => 'NEGASIT']));
        $this->expectException(EroareNegasit::class);
        Ajutor::client($c2)->vehicule->sterge('x');
    }

    public function testStergeFaraId(): void
    {
        $client = new ClientFals();
        try {
            Ajutor::client($client)->vehicule->sterge('  ');
            self::fail('trebuia sa arunce');
        } catch (EroareValidare $e) {
            self::assertSame('id', $e->camp);
        }
        self::assertCount(0, $client->cereri);
    }

    public function testStergeCuRaspunsNeasteptat(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, ['success' => false]));
        $this->expectException(EroareRaspunsInvalid::class);
        Ajutor::client($client)->vehicule->sterge('x');
    }
}
