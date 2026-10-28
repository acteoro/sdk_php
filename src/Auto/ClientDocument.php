<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Cerere\ReferintaVerificare;
use Acteo\ClientVerificari;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;
use Acteo\Validare;

/**
 * Baza comuna a clientilor pe document (`ClientRca`, `ClientItp`,
 * `ClientRovinieta`, `ClientArr`). Fiecare subclasa transforma identificatorul
 * documentului intr-o cerere; validarea, reincercarile si pollingul raman cele
 * din `ClientVerificari`.
 */
abstract class ClientDocument
{
    /** @internal */
    public function __construct(private readonly ClientVerificari $verificari) {}

    /** Documentul verificat de acest client. */
    abstract public function tip(): TipVerificare;

    final protected function verificaCerere(CerereVerificare $cerere, ?OptiuniAsteptare $optiuni): VerificareFinala
    {
        return $this->verificari->verificaSiAsteapta($cerere, $optiuni);
    }

    final protected function pornesteCerere(CerereVerificare $cerere): VerificareFinala|VerificareInCurs
    {
        return $this->verificari->porneste($cerere);
    }

    final protected function rezultatPentru(VerificareInCurs|CerereVerificare $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->verificari->rezultat($this->referinta($referinta));
    }

    final protected function asteaptaPentru(VerificareInCurs|CerereVerificare $referinta, ?OptiuniAsteptare $optiuni): VerificareFinala
    {
        return $this->verificari->asteapta($this->referinta($referinta), $optiuni);
    }

    /**
     * Verificarea in curs merge mai departe neschimbata (cu `since`); un
     * identificator e validat local si devine referinta de polling.
     */
    private function referinta(VerificareInCurs|CerereVerificare $x): VerificareInCurs|ReferintaVerificare
    {
        if ($x instanceof VerificareInCurs) {
            if ($x->tip !== $this->tip()) {
                throw new EroareValidare(
                    "Verificarea in curs este de tip {$x->tip->value}, nu {$this->tip()->value}.",
                    cod: 'VALIDARE_LOCALA',
                    camp: 'referinta',
                );
            }

            return $x;
        }
        $tinta = Validare::valideazaCerereVerificare($x);

        // La RCA cu VIN, API-ul cauta dupa VIN, deci si pollingul merge pe VIN.
        return new ReferintaVerificare(
            $tinta->tip,
            $tinta->queryPolling,
            $tinta->tip === TipVerificare::Rovinieta ? $tinta->serieSasiu : null,
        );
    }
}
