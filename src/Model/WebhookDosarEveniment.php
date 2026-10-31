<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Evenimentul `dosar.eveniment`: noutate intr-un dosar monitorizat. */
final class WebhookDosarEveniment implements WebhookNotificare
{
    public const EVENIMENT = 'dosar.eveniment';

    /** Mereu `"dosar.eveniment"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param DosarWebhook $dosar dosarul monitorizat
     * @param EvenimentDosar $eveniment ce s-a intamplat in dosar
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly DosarWebhook $dosar,
        public readonly EvenimentDosar $eveniment,
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
            dosar: DosarWebhook::fromArray(Decodor::obiect($d, 'dosar', ''), 'dosar'),
            eveniment: EvenimentDosar::fromArray(Decodor::obiect($d, 'eveniment', ''), 'eveniment'),
        );
    }
}
