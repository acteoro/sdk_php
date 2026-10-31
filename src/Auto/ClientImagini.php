<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereImagine;
use Acteo\Enum\StatusImagine;
use Acteo\Enum\UnghiImagine;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Internal\Ceas;
use Acteo\Internal\Erori;
use Acteo\Internal\Http;
use Acteo\Model\ImagineMasina;
use Acteo\Validare;

/** Pozele de studio ale masinilor. Cere un abonament de imagini masini. Il accesezi prin `$acteo->auto->imagini`. */
final class ClientImagini
{
    private const CALE = '/api/public/v1/imagini-masini';

    /** Cat asteapta implicit `asteapta()` o poza in lucru: 2 minute. */
    public const TIMEOUT_IMPLICIT_MS = 120000;

    // Pauza cand serverul nu trimite `Retry-After` si plafonul de jos, ca sa nu
    // consumam plafonul zilnic de poze generice.
    private const PAUZA_MINIMA_MS = 10000;

    /** @internal */
    public function __construct(
        private readonly Http $http,
        private readonly Ceas $ceas,
    ) {}

    /**
     * Cere poza unei masini si raspunde imediat. Cu status `Gata` primesti poza
     * reala si se taxeaza; altfel primesti poza generica (masina sub husa),
     * netaxata. La `InLucru`, cere din nou dupa `retryAfterMs` sau foloseste
     * `asteapta()`. Linkul din `url` expira la `expiraLa`.
     *
     * Exemplu:
     *     $p = $acteo->auto->imagini->obtine(['marca' => 'Volkswagen', 'model' => 'Golf', 'an' => 2015]);
     *     if ($p->status === StatusImagine::Gata) { echo $p->url; }
     *
     * @param array{marca?: string, model?: string, an?: int, unghi?: UnghiImagine|string, umbra?: bool}|CerereImagine $cerere
     *   `marca`, `model`, `an` si optional `unghi`, `umbra`
     *
     * @throws \Acteo\Exception\EroareValidare marca, model, an, unghi sau umbra invalide
     * @throws \Acteo\Exception\EroarePlanInactiv lipseste abonamentul de imagini masini
     * @throws \Acteo\Exception\EroareLimita cota atinsa (LIMIT_ATINS) sau prea multe poze generice azi (PLAFON_NETAXAT)
     */
    public function obtine(array|CerereImagine $cerere): ImagineMasina
    {
        $corp = Validare::valideazaCerereImagine(CerereImagine::din($cerere));
        $raspuns = $this->http->trimite('POST', self::CALE, false, [], $corp);
        $limite = $raspuns->limite();
        $retryAfterMs = Erori::retryAfterMs($raspuns->header('retry-after'), $this->ceas->acumMs());

        return Http::decodeaza(
            $raspuns,
            static fn(array $d): ImagineMasina => ImagineMasina::fromArray($d, $limite, $retryAfterMs),
        );
    }

    /**
     * Ca `obtine()`, dar cat timp poza e `InLucru` o cere din nou, cu pauzele din
     * `Retry-After` (cel putin 10 secunde). Fiecare cerere intermediara e netaxata,
     * dar intra in plafonul zilnic de poze generice.
     *
     * @param array{marca?: string, model?: string, an?: int, unghi?: UnghiImagine|string, umbra?: bool}|CerereImagine $cerere
     * @param int $timeoutMs cat asteptam in total; implicit 2 minute
     *
     * @return ImagineMasina poza cu orice status in afara de `InLucru`
     *
     * @throws EroareTimeoutVerificare poza nu a fost gata in `timeoutMs` (TIMEOUT_IMAGINE)
     */
    public function asteapta(array|CerereImagine $cerere, int $timeoutMs = self::TIMEOUT_IMPLICIT_MS): ImagineMasina
    {
        $cerere = CerereImagine::din($cerere);
        $termen = $this->ceas->acumMs() + $timeoutMs;
        while (true) {
            $raspuns = $this->obtine($cerere);
            if ($raspuns->status !== StatusImagine::InLucru) {
                return $raspuns;
            }
            $pauza = max(self::PAUZA_MINIMA_MS, $raspuns->retryAfterMs ?? 0);
            if ($this->ceas->acumMs() + $pauza > $termen) {
                throw new EroareTimeoutVerificare(
                    "Poza nu a fost gata in {$timeoutMs} ms. Cere-o din nou mai tarziu.",
                    cod: 'TIMEOUT_IMAGINE',
                );
            }
            $this->ceas->dormiMs($pauza);
        }
    }
}
