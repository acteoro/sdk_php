<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Vehiculul la care se refera o notificare `document.expira`. */
final class VehiculWebhook
{
    /**
     * @param string $id ID-ul vehiculului in cont, acelasi ca in `vehicule->lista()`
     * @param string|null $numarInmatriculare numarul, daca e cunoscut
     * @param string|null $serieSasiu VIN-ul, daca e cunoscut
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $numarInmatriculare,
        public readonly ?string $serieSasiu,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            id: Decodor::sir($d, 'id', $prefix),
            numarInmatriculare: Decodor::sirSauNull($d, 'numarInmatriculare', $prefix),
            serieSasiu: Decodor::sirSauNull($d, 'serieSasiu', $prefix),
        );
    }
}
