<?php

declare(strict_types=1);

// Teste pe API-ul real. Configurarea sta in .env.e2e (vezi .env.e2e.example).
// Variabilele deja setate in mediu au prioritate fata de fisier.

require __DIR__ . '/../vendor/autoload.php';

$fisier = __DIR__ . '/../.env.e2e';
if (is_file($fisier)) {
    foreach (file($fisier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linie) {
        $linie = trim($linie);
        if ($linie === '' || str_starts_with($linie, '#') || !str_contains($linie, '=')) {
            continue;
        }
        [$nume, $valoare] = explode('=', $linie, 2);
        $nume = trim($nume);
        if (getenv($nume) === false) {
            putenv($nume . '=' . trim($valoare));
        }
    }
}
