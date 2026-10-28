<?php

declare(strict_types=1);

// Verificare RCA dupa numar de inmatriculare si VIN, cu asteptarea rezultatului.
// Rulare: ACTEO_API_KEY=... php examples/verificare-rca.php

require __DIR__ . '/../vendor/autoload.php';

use Acteo\Acteo;
use Acteo\Exception\EroareLimita;
use Acteo\Exception\EroareValidare;

$acteo = new Acteo();

try {
    $rca = $acteo->auto->rca->verifica(['numar' => 'B123ABC', 'vin' => 'WVWZZZ1JZXW000001']);
    echo 'RCA ', $rca->status->value, ', expira la ', $rca->expiresAt?->format('d.m.Y') ?? '-', PHP_EOL;
    echo 'Mai ai ', $rca->limite?->zi->ramase ?? '?', ' verificari azi.', PHP_EOL;
} catch (EroareValidare $e) {
    fwrite(STDERR, 'Date gresite: ' . $e->getMessage() . PHP_EOL);
} catch (EroareLimita $e) {
    fwrite(STDERR, 'Cota atinsa: ' . $e->utilizareZi . PHP_EOL);
}
