<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\TipVerificare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;

/**
 * Inspectia tehnica periodica, din RAR. Accepta doar seria de sasiu (VIN):
 * RAR nu cauta dupa numarul de inmatriculare. Il accesezi prin `$acteo->auto->itp`.
 *
 * `verifica()` si `porneste()` consuma din cota planului; `rezultat()` si
 * `asteapta()` sunt gratuite.
 */
final class ClientItp extends ClientDocument
{
    public function tip(): TipVerificare
    {
        return TipVerificare::Itp;
    }

    /**
     * Verifica ITP-ul si asteapta rezultatul final de la sursa oficiala.
     *
     * @param string $vin seria de sasiu, 17 caractere
     *
     * @throws \Acteo\Exception\EroareValidare VIN invalid (inclusiv un numar de inmatriculare), local
     * @throws \Acteo\Exception\EroareLimita cota zilnica sau lunara atinsa
     * @throws \Acteo\Exception\EroareVerificare sursa oficiala nu a putut fi interogata sau a refuzat datele
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function verifica(string $vin, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->verificaCerere(self::cerere($vin), $optiuni);
    }

    /**
     * Porneste verificarea fara sa astepte.
     *
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     */
    public function porneste(string $vin, ?string $callbackUrl = null): VerificareFinala|VerificareInCurs
    {
        return $this->pornesteCerere(self::cerere($vin, $callbackUrl));
    }

    /**
     * Interogheaza o singura data rezultatul. Nu consuma din cota.
     *
     * @param VerificareInCurs|string $referinta verificarea primita de la `porneste()` sau VIN-ul
     */
    public function rezultat(VerificareInCurs|string $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->rezultatPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta));
    }

    /**
     * Interogheaza periodic pana la rezultatul final. Nu consuma din cota.
     *
     * @param VerificareInCurs|string $referinta verificarea primita de la `porneste()` sau VIN-ul
     *
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function asteapta(VerificareInCurs|string $referinta, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->asteaptaPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta), $optiuni);
    }

    private static function cerere(string $vin, ?string $callbackUrl = null): CerereVerificare
    {
        return new CerereVerificare(TipVerificare::Itp, $vin, null, $callbackUrl);
    }
}
