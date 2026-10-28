<?php

declare(strict_types=1);

// Raport de expirari pentru toata flota: RCA, ITP, rovinieta si ARR in urmatoarele 30 de zile.
// Rulare: ACTEO_API_KEY=... php examples/flota.php

require __DIR__ . '/../vendor/autoload.php';

use Acteo\Acteo;

$acteo = new Acteo();
$azi = new DateTimeImmutable('today');
$limita = $azi->modify('+30 days');

foreach ($acteo->auto->flota->lista() as $v) {
    $documente = [
        'RCA' => $v->rcaExpiraLa,
        'ITP' => $v->itpExpiraLa,
        'Rovinieta' => $v->rovinietaExpiraLa,
        'ARR' => $v->arrExpiraLa,
    ];
    foreach ($documente as $nume => $data) {
        if ($data !== null && $data <= $limita) {
            $zile = (int) $azi->diff($data)->format('%r%a');
            echo ($v->numarInmatriculare ?? $v->serieSasiu), ": {$nume} expira in {$zile} zile", PHP_EOL;
        }
    }
}
