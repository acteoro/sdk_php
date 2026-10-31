<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\CifraControl;
use Acteo\Internal\Decodor;

/** Structura unui VIN, calculata doar din cele 17 caractere. */
final class StructuraVin
{
    /**
     * @param string $wmi pozitiile 1-3: producatorul
     * @param string $vds pozitiile 4-9: descrierea vehiculului
     * @param string $vis pozitiile 10-17: exemplarul
     * @param string|null $regiune regiunea producatorului
     * @param string|null $tara tara producatorului
     * @param CifraControl $cifraControl cifra de control (pozitia 9)
     * @param string|null $cifraControlAsteptata cifra de control asteptata, cand se aplica
     * @param list<int> $aniPosibili anii de fabricatie posibili dupa pozitia 10
     */
    public function __construct(
        public readonly string $wmi,
        public readonly string $vds,
        public readonly string $vis,
        public readonly ?string $regiune,
        public readonly ?string $tara,
        public readonly CifraControl $cifraControl,
        public readonly ?string $cifraControlAsteptata,
        public readonly array $aniPosibili,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            wmi: Decodor::sir($d, 'wmi', $prefix),
            vds: Decodor::sir($d, 'vds', $prefix),
            vis: Decodor::sir($d, 'vis', $prefix),
            regiune: Decodor::sirSauNull($d, 'regiune', $prefix),
            tara: Decodor::sirSauNull($d, 'tara', $prefix),
            cifraControl: Decodor::enum($d, 'cifraControl', $prefix, CifraControl::class),
            cifraControlAsteptata: Decodor::sirSauNull($d, 'cifraControlAsteptata', $prefix),
            aniPosibili: Decodor::listaIntregi($d, 'aniPosibili', $prefix),
        );
    }
}
