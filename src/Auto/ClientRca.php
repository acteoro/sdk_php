<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\TipVerificare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;

/**
 * Asigurarea RCA, din baza AIDA. Accepta numar de inmatriculare, VIN sau
 * amandoua. Il accesezi prin `$acteo->auto->rca`, nu il construiesti direct.
 *
 * `verifica()` si `porneste()` consuma din cota planului; `rezultat()` si
 * `asteapta()` sunt gratuite.
 *
 * Identificatorul poate fi:
 *   - un text: 17 caractere fara I, O si Q inseamna VIN, altfel numar de inmatriculare;
 *   - `['numar' => 'B123ABC', 'vin' => 'WVWZZZ1JZXW000001']` sau `new IdentificatorRca(...)`.
 */
final class ClientRca extends ClientDocument
{
    public function tip(): TipVerificare
    {
        return TipVerificare::Rca;
    }

    /**
     * Verifica polita RCA si asteapta rezultatul final de la sursa oficiala.
     *
     * @param string|array{numar?: string|null, vin?: string|null}|IdentificatorRca $identificator
     *
     * @throws \Acteo\Exception\EroareValidare identificator invalid, local sau HTTP 400
     * @throws \Acteo\Exception\EroareLimita cota zilnica sau lunara atinsa
     * @throws \Acteo\Exception\EroareVerificare sursa oficiala nu a putut fi interogata sau a refuzat datele
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function verifica(string|array|IdentificatorRca $identificator, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->verificaCerere(self::cerere($identificator), $optiuni);
    }

    /**
     * Porneste verificarea fara sa astepte. Primesti rezultatul imediat sau o
     * `VerificareInCurs`, pe care o dai apoi la `asteapta()` sau `rezultat()`.
     *
     * @param string|array{numar?: string|null, vin?: string|null}|IdentificatorRca $identificator
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     */
    public function porneste(string|array|IdentificatorRca $identificator, ?string $callbackUrl = null): VerificareFinala|VerificareInCurs
    {
        return $this->pornesteCerere(self::cerere($identificator, $callbackUrl));
    }

    /**
     * Interogheaza o singura data rezultatul. Nu consuma din cota.
     *
     * @param VerificareInCurs|string|array{numar?: string|null, vin?: string|null}|IdentificatorRca $referinta
     *   verificarea primita de la `porneste()` sau identificatorul; cu VIN, interogarea se face dupa VIN
     */
    public function rezultat(VerificareInCurs|string|array|IdentificatorRca $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->rezultatPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta));
    }

    /**
     * Interogheaza periodic pana la rezultatul final. Nu consuma din cota.
     *
     * @param VerificareInCurs|string|array{numar?: string|null, vin?: string|null}|IdentificatorRca $referinta
     *
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function asteapta(VerificareInCurs|string|array|IdentificatorRca $referinta, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->asteaptaPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta), $optiuni);
    }

    /** @param string|array{numar?: string|null, vin?: string|null}|IdentificatorRca $identificator */
    private static function cerere(string|array|IdentificatorRca $identificator, ?string $callbackUrl = null): CerereVerificare
    {
        $id = IdentificatorRca::din($identificator);
        $numar = trim($id->numar ?? '');
        $vin = trim($id->vin ?? '');

        // Cu numar si VIN, numarul merge in `query` si VIN-ul in `serieSasiu`.
        return $numar !== ''
            ? new CerereVerificare(TipVerificare::Rca, $numar, $vin !== '' ? $vin : null, $callbackUrl)
            : new CerereVerificare(TipVerificare::Rca, $vin, null, $callbackUrl);
    }
}
