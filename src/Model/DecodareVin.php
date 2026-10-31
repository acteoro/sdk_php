<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\SursaAn;
use Acteo\Internal\Decodor;

/** Rezultatul decodarii unui VIN. */
final class DecodareVin
{
    /**
     * @param string $vin VIN-ul normalizat
     * @param bool $gasit marca e cunoscuta
     * @param bool $taxat s-a consumat o cerere din plan: doar cand marca si modelul sunt gasite
     * @param string|null $marca marca, cand e gasita
     * @param string|null $model modelul, cand e gasit
     * @param int|null $anFabricatie anul fabricatiei, cand e cunoscut
     * @param SursaAn|null $sursaAn de unde vine anul
     * @param list<string> $modelePosibile modelele posibile, cand sursele nu cad de acord; mereu gol cand `taxat` e false
     * @param StructuraVin $structura structura VIN-ului
     * @param Limite|null $limite cota ramasa dupa aceasta cerere, cand API-ul o trimite
     */
    public function __construct(
        public readonly string $vin,
        public readonly bool $gasit,
        public readonly bool $taxat,
        public readonly ?string $marca,
        public readonly ?string $model,
        public readonly ?int $anFabricatie,
        public readonly ?SursaAn $sursaAn,
        public readonly array $modelePosibile,
        public readonly StructuraVin $structura,
        public readonly ?Limite $limite = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, ?Limite $limite = null): self
    {
        return new self(
            vin: Decodor::sir($d, 'vin', ''),
            gasit: Decodor::bool($d, 'gasit', ''),
            taxat: Decodor::bool($d, 'taxat', ''),
            marca: Decodor::sirSauNull($d, 'marca', ''),
            model: Decodor::sirSauNull($d, 'model', ''),
            anFabricatie: Decodor::intregSauNull($d, 'anFabricatie', ''),
            sursaAn: Decodor::enumSauNull($d, 'sursaAn', '', SursaAn::class),
            modelePosibile: Decodor::listaSiruri($d, 'modelePosibile', ''),
            structura: StructuraVin::fromArray(Decodor::obiect($d, 'structura', ''), 'structura'),
            limite: $limite,
        );
    }
}
