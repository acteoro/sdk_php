<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatusImagine;
use Acteo\Enum\UnghiImagine;
use Acteo\Internal\Decodor;

/** Poza unei masini, cu cota ramasa si pauza recomandata. */
final class ImagineMasina
{
    /**
     * @param bool $gata poza e a masinii cerute; false = poza generica (masina sub husa)
     * @param StatusImagine $status starea pozei
     * @param bool $taxat s-a consumat o cerere din plan: doar cand `status` e `Gata`
     * @param string|null $url link semnat spre poza (WebP transparent), valabil pana la `expiraLa`;
     *   null doar daca nici poza generica nu e disponibila
     * @param \DateTimeImmutable|null $expiraLa cand expira `url`; null cand `url` e null
     * @param UnghiImagine $unghi unghiul pozei
     * @param bool $umbra poza are umbra sub masina
     * @param int $latime latimea pozei, in pixeli
     * @param int $inaltime inaltimea pozei, in pixeli
     * @param string $marca marca canonica, sau cea trimisa daca inca nu e cunoscuta
     * @param string $model modelul canonic, sau cel trimis daca inca nu e cunoscut
     * @param GeneratieMasina|null $generatie generatia aleasa dupa an; null cand nu e aleasa inca
     * @param Limite|null $limite cota ramasa dupa aceasta cerere, cand API-ul o trimite
     * @param int|null $retryAfterMs la `InLucru`: dupa cat timp sa ceri din nou poza, din `Retry-After`
     */
    public function __construct(
        public readonly bool $gata,
        public readonly StatusImagine $status,
        public readonly bool $taxat,
        public readonly ?string $url,
        public readonly ?\DateTimeImmutable $expiraLa,
        public readonly UnghiImagine $unghi,
        public readonly bool $umbra,
        public readonly int $latime,
        public readonly int $inaltime,
        public readonly string $marca,
        public readonly string $model,
        public readonly ?GeneratieMasina $generatie,
        public readonly ?Limite $limite = null,
        public readonly ?int $retryAfterMs = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, ?Limite $limite = null, ?int $retryAfterMs = null): self
    {
        $status = Decodor::enum($d, 'status', '', StatusImagine::class);
        $generatie = Decodor::obiectSauNull($d, 'generatie', '');

        return new self(
            gata: Decodor::bool($d, 'gata', ''),
            status: $status,
            taxat: Decodor::bool($d, 'taxat', ''),
            url: Decodor::sirSauNull($d, 'url', ''),
            expiraLa: Decodor::dataSauNull($d, 'expiraLa', ''),
            unghi: Decodor::enum($d, 'unghi', '', UnghiImagine::class),
            umbra: Decodor::bool($d, 'umbra', ''),
            latime: Decodor::intreg($d, 'latime', ''),
            inaltime: Decodor::intreg($d, 'inaltime', ''),
            marca: Decodor::sir($d, 'marca', ''),
            model: Decodor::sir($d, 'model', ''),
            generatie: $generatie === null ? null : GeneratieMasina::fromArray($generatie, 'generatie'),
            limite: $limite,
            retryAfterMs: $status === StatusImagine::InLucru ? $retryAfterMs : null,
        );
    }
}
