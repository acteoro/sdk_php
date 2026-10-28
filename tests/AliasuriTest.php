<?php

declare(strict_types=1);

namespace Acteo\Tests;

use Acteo\Tests\Ajutor\Ajutor;
use Acteo\Tests\Ajutor\ClientFals;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/** Codul scris pentru pachetul vechi, cu numele `VerificRca\...`, merge neschimbat. */
final class AliasuriTest extends TestCase
{
    private const NUME_VECHI = [
        'VerificRca', 'ClientVerificari', 'ClientVehicule', 'Validare', 'Webhooks',
        'Cerere\CerereVerificare', 'Cerere\OptiuniAsteptare', 'Cerere\ReferintaVerificare', 'Cerere\VehiculNou',
        'Enum\StatusDocument', 'Enum\StatusWebhook', 'Enum\TipVerificare',
        'Exception\VerificRcaException', 'Exception\EroareAutentificare', 'Exception\EroareConfigurare',
        'Exception\EroareConflict', 'Exception\EroareHttp', 'Exception\EroareInterzis', 'Exception\EroareLimita',
        'Exception\EroareNegasit', 'Exception\EroarePlanInactiv', 'Exception\EroareRaspunsInvalid',
        'Exception\EroareRetea', 'Exception\EroareServer', 'Exception\EroareTimeoutVerificare',
        'Exception\EroareValidare', 'Exception\EroareVerificare', 'Exception\EroareWebhook',
        'Internal\Ceas', 'Internal\CeasSistem',
        'Model\Cota', 'Model\Limite', 'Model\Vehicul', 'Model\VehiculWebhook', 'Model\VerificareFinala',
        'Model\VerificareInCurs', 'Model\WebhookDocumentExpira', 'Model\WebhookRezultat', 'Model\WebhookTest',
    ];

    public function testToateNumeleVechiExista(): void
    {
        foreach (self::NUME_VECHI as $nume) {
            $vechi = 'VerificRca\\' . $nume;
            // Fara autoload: aliasul trebuie sa existe deja, altfel `catch` nu l-ar vedea.
            self::assertTrue(class_exists($vechi, false) || interface_exists($vechi, false), $vechi);
        }
        self::assertSame(\Acteo\Exception\ActeoException::class, (new \ReflectionClass(\VerificRca\Exception\VerificRcaException::class))->getName());
    }

    public function testCodulVechiMergeNeschimbat(): void
    {
        $client = new ClientFals(ClientFals::raspuns(200, Ajutor::REZULTAT_VALID));
        $fabrica = new HttpFactory();
        $vrca = new \VerificRca\VerificRca(apiKey: Ajutor::CHEIE, httpClient: $client, requestFactory: $fabrica, streamFactory: $fabrica);
        $r = $vrca->verificari->porneste(new \VerificRca\Cerere\CerereVerificare(\VerificRca\Enum\TipVerificare::Rca, 'B123ABC'));

        self::assertInstanceOf(\VerificRca\Model\VerificareFinala::class, $r);
        self::assertSame(\VerificRca\Enum\StatusDocument::Valid, $r->status);
        self::assertSame(\Acteo\Enum\StatusDocument::Valid, \VerificRca\Enum\StatusDocument::from('valid'));
        self::assertSame('https://www.verificrca.ro', \VerificRca\VerificRca::URL_IMPLICIT);
    }

    public function testExceptiileSePrindCuNumeleVechi(): void
    {
        $prinsa = null;
        try {
            Ajutor::acteo(new ClientFals())->auto->itp->verifica('B123ABC');
        } catch (\VerificRca\Exception\EroareValidare $e) {
            $prinsa = $e;
        }
        self::assertInstanceOf(\VerificRca\Exception\VerificRcaException::class, $prinsa);
        self::assertInstanceOf(\Acteo\Exception\ActeoException::class, $prinsa);
    }
}
