<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\ClientVehicule;
use Acteo\ClientVerificari;
use Acteo\Internal\Ceas;
use Acteo\Internal\Http;

/** Domeniul auto: documentele unei masini si flota monitorizata. Il accesezi prin `$acteo->auto`. */
final class ClientAuto
{
    /** Asigurarea RCA: `$acteo->auto->rca->verifica('B123ABC')`. */
    public readonly ClientRca $rca;

    /** ITP: `$acteo->auto->itp->verifica('WVWZZZ1JZXW000001')`. */
    public readonly ClientItp $itp;

    /** Rovinieta: `$acteo->auto->rovinieta->verifica(['numar' => ..., 'vin' => ..., 'serieTalon' => ...])`. */
    public readonly ClientRovinieta $rovinieta;

    /** Copia conforma ARR: `$acteo->auto->arr->verifica('B123ABC')`. */
    public readonly ClientArr $arr;

    /** Vehiculele monitorizate din cont: `lista()`, `adauga()`, `sterge()`. */
    public readonly ClientVehicule $flota;

    /** @internal */
    public function __construct(Http $http, Ceas $ceas)
    {
        $verificari = new ClientVerificari($http, $ceas);
        $this->rca = new ClientRca($verificari);
        $this->itp = new ClientItp($verificari);
        $this->rovinieta = new ClientRovinieta($verificari);
        $this->arr = new ClientArr($verificari);
        $this->flota = new ClientVehicule($http);
    }
}
