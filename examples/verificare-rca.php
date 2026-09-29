<?php

declare(strict_types=1);

// Verificare RCA dupa numar de inmatriculare si VIN, cu asteptarea rezultatului.
// Rulare: VERIFICRCA_API_KEY=vrca_... php examples/verificare-rca.php

require __DIR__ . '/../vendor/autoload.php';

use VerificRca\Cerere\CerereVerificare;
use VerificRca\Enum\TipVerificare;
use VerificRca\Exception\EroareLimita;
use VerificRca\Exception\EroareValidare;
use VerificRca\VerificRca;

$vrca = new VerificRca();

try {
    $rca = $vrca->verificari->verificaSiAsteapta(new CerereVerificare(
        tip: TipVerificare::Rca,
        query: 'B123ABC',
        serieSasiu: 'WVWZZZ1JZXW000001',
    ));
    echo 'RCA ', $rca->status->value, ', expira la ', $rca->expiresAt?->format('d.m.Y') ?? '-', PHP_EOL;
    echo 'Mai ai ', $rca->limite?->zi->ramase ?? '?', ' verificari azi.', PHP_EOL;
} catch (EroareValidare $e) {
    fwrite(STDERR, 'Date gresite: ' . $e->getMessage() . PHP_EOL);
} catch (EroareLimita $e) {
    fwrite(STDERR, 'Cota atinsa: ' . $e->utilizareZi . PHP_EOL);
}
