<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatusDocument;
use Acteo\Enum\TipVerificare;
use Acteo\Internal\Decodor;

/** Rezultatul final al unei verificari. */
final class VerificareFinala
{
    /**
     * @param TipVerificare $tip tipul verificarii
     * @param string $query identificatorul dupa care s-a cautat (la RCA cu VIN, acesta e VIN-ul)
     * @param string|null $numarInmatriculare numarul verificat, null cand cautarea s-a facut doar dupa VIN
     * @param string|null $serieSasiu VIN-ul verificat, null cand cautarea s-a facut doar dupa numar
     * @param \DateTimeImmutable $checkedAt cand a fost interogata sursa oficiala
     * @param StatusDocument $status starea documentului
     * @param \DateTimeImmutable|null $expiresAt data expirarii, null daca nu exista document valabil
     * @param string|null $requestId prezent doar cand ai trimis `callbackUrl`; apare si in webhook
     * @param string|null $hint recomandarea API-ului, ex: la RCA cerut doar dupa numar, sa trimiti si VIN-ul
     * @param Limite|null $limite cota ramasa dupa aceasta cerere, cand API-ul o trimite
     */
    public function __construct(
        public readonly TipVerificare $tip,
        public readonly string $query,
        public readonly ?string $numarInmatriculare,
        public readonly ?string $serieSasiu,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly StatusDocument $status,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?string $requestId = null,
        public readonly ?string $hint = null,
        public readonly ?Limite $limite = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix = '', ?Limite $limite = null): self
    {
        return new self(
            tip: Decodor::enum($d, 'tip', $prefix, TipVerificare::class),
            query: Decodor::sir($d, 'query', $prefix),
            numarInmatriculare: Decodor::sirSauNull($d, 'numarInmatriculare', $prefix),
            serieSasiu: Decodor::sirSauNull($d, 'serieSasiu', $prefix),
            checkedAt: Decodor::data($d, 'checkedAt', $prefix),
            status: Decodor::enum($d, 'status', $prefix, StatusDocument::class),
            expiresAt: Decodor::dataSauNull($d, 'expiresAt', $prefix),
            requestId: Decodor::sirOptional($d, 'requestId', $prefix),
            hint: Decodor::sirOptional($d, 'hint', $prefix),
            limite: $limite,
        );
    }
}
