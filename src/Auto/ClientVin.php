<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Internal\Http;
use Acteo\Model\DecodareVin;
use Acteo\Validare;

/** Decodarea seriei de sasiu (VIN). Cere un abonament de decodare VIN. Il accesezi prin `$acteo->auto->vin`. */
final class ClientVin
{
    private const CALE = '/api/public/v1/decodare-vin';

    /** @internal */
    public function __construct(private readonly Http $http) {}

    /**
     * Decodeaza un VIN: marca, model, an si structura lui. Consuma din cota doar
     * cand marca si modelul sunt gasite (`taxat` e true).
     *
     * Exemplu:
     *     $d = $acteo->auto->vin->decodeaza('WVWZZZ1KZBW000000');
     *     echo $d->marca, ' ', $d->model, ' ', $d->anFabricatie;
     *
     * @param string $vin seria de sasiu, 17 caractere (spatiile si literele mici sunt acceptate)
     *
     * @throws \Acteo\Exception\EroareValidare VIN invalid, local sau HTTP 400
     * @throws \Acteo\Exception\EroarePlanInactiv lipseste abonamentul de decodare VIN
     * @throws \Acteo\Exception\EroareLimita cota atinsa (LIMIT_ATINS) sau prea multe decodari fara rezultat (PLAFON_NETAXAT)
     */
    public function decodeaza(string $vin): DecodareVin
    {
        $raspuns = $this->http->trimite('POST', self::CALE, false, [], ['vin' => Validare::valideazaVinDecodare($vin)]);
        $limite = $raspuns->limite();

        return Http::decodeaza($raspuns, static fn(array $d): DecodareVin => DecodareVin::fromArray($d, $limite));
    }
}
