<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatutAsigurare;
use Acteo\Internal\Decodor;

/** Evenimentul `asigurare.schimbare`: s-a schimbat statutul de asigurat la CNAS al unei persoane monitorizate. */
final class WebhookAsigurareSchimbare implements WebhookNotificare
{
    public const EVENIMENT = 'asigurare.schimbare';

    /** Mereu `"asigurare.schimbare"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param PersoanaWebhook $persoana persoana monitorizata
     * @param StatutAsigurare $statut statutul de acum
     * @param bool|null $areCard cardul de sanatate e emis; null cand CNAS nu spune
     * @param \DateTimeImmutable $verificatLa cand a fost interogat CNAS
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly PersoanaWebhook $persoana,
        public readonly StatutAsigurare $statut,
        public readonly ?bool $areCard,
        public readonly \DateTimeImmutable $verificatLa,
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
            persoana: PersoanaWebhook::fromArray(Decodor::obiect($d, 'persoana', ''), 'persoana'),
            statut: Decodor::enum($d, 'statut', '', StatutAsigurare::class),
            areCard: Decodor::boolSauNull($d, 'areCard', ''),
            verificatLa: Decodor::data($d, 'verificatLa', ''),
        );
    }
}
