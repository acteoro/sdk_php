<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\SchimbareMarca;
use Acteo\Internal\Decodor;

/** Evenimentul `marca.schimbare`: s-a schimbat ceva la o marca urmarita (OSIM sau EUIPO). */
final class WebhookMarcaSchimbare implements WebhookNotificare
{
    public const EVENIMENT = 'marca.schimbare';

    /** Mereu `"marca.schimbare"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param MarcaWebhook $marca marca urmarita
     * @param SchimbareMarca $schimbare ce s-a schimbat
     * @param string $valoareVeche la `Reinnoita`: vechea data de expirare (`yyyy-mm-dd`); la `Opozitie`:
     *   numarul vechi de opozitii; altfel statusul vechi
     * @param string $valoareNoua ca `valoareVeche`, valoarea de acum
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly MarcaWebhook $marca,
        public readonly SchimbareMarca $schimbare,
        public readonly string $valoareVeche,
        public readonly string $valoareNoua,
    ) {
        $this->event = self::EVENIMENT;
    }

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            id: Decodor::sir($d, 'id', ''),
            trimisLa: Decodor::data($d, 'trimisLa', ''),
            marca: MarcaWebhook::fromArray(Decodor::obiect($d, 'marca', ''), 'marca'),
            schimbare: Decodor::enum($d, 'schimbare', '', SchimbareMarca::class),
            valoareVeche: Decodor::sir($d, 'valoareVeche', ''),
            valoareNoua: Decodor::sir($d, 'valoareNoua', ''),
        );
    }
}
