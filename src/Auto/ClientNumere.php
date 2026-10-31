<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Internal\Http;
use Acteo\Model\NumarDisponibil;
use Acteo\Validare;

/**
 * Numerele de inmatriculare disponibile, direct de la DGPCI. Cere un abonament de
 * numere disponibile. Il accesezi prin `$acteo->auto->numere`.
 */
final class ClientNumere
{
    private const CALE = '/api/public/v1/numere-disponibile';

    // DGPCI raspunde sincron si poate dura pana la doua minute.
    private const TIMEOUT_MINIM_MS = 150000;

    /** @internal */
    public function __construct(private readonly Http $http) {}

    /**
     * Verifica la DGPCI daca un numar de inmatriculare e liber. Raspunsul vine
     * sincron si poate dura pana la doua minute; clientul construit de SDK foloseste
     * pentru aceasta cerere un timeout de cel putin 150 de secunde, oricat de mic e
     * `timeoutMs`. Un `$httpClient` injectat isi pastreaza timeout-ul propriu.
     * Consuma din cota doar cand DGPCI da un verdict.
     *
     * Exemplu:
     *     $r = $acteo->auto->numere->verifica('BV07ABC');
     *     if ($r->disponibil) { echo 'Numarul e liber'; }
     *
     * @param string $numar numarul complet: judetul, cifrele si trei litere, ex: `BV07ABC`
     *
     * @throws \Acteo\Exception\EroareValidare numar invalid sau incomplet, ori refuzat de DGPCI (NUMAR_RESPINS)
     * @throws \Acteo\Exception\EroarePlanInactiv lipseste abonamentul de numere disponibile
     * @throws \Acteo\Exception\EroareLimita cota atinsa
     * @throws \Acteo\Exception\EroareServer OCUPAT, INDISPONIBIL, EROARE_DGPCI sau TIMEOUT; cererea nu s-a taxat
     */
    public function verifica(string $numar): NumarDisponibil
    {
        $raspuns = $this->http->trimite(
            'POST',
            self::CALE,
            false,
            [],
            ['numar' => Validare::valideazaNumarDisponibil($numar)],
            timeoutMinimMs: self::TIMEOUT_MINIM_MS,
        );
        $limite = $raspuns->limite();

        return Http::decodeaza($raspuns, static fn(array $d): NumarDisponibil => NumarDisponibil::fromArray($d, $limite));
    }
}
