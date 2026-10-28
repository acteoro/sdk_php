<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Evenimentul `webhook.test`, trimis din butonul de test din dashboard. */
final class WebhookTest
{
    public const EVENIMENT = 'webhook.test';

    /** Mereu `"webhook.test"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al testului
     * @param string $mesaj mesajul de confirmare
     * @param \DateTimeImmutable $trimisLa cand a fost trimis testul
     */
    public function __construct(
        public readonly string $id,
        public readonly string $mesaj,
        public readonly \DateTimeImmutable $trimisLa,
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
            mesaj: Decodor::sir($d, 'mesaj', ''),
            trimisLa: Decodor::data($d, 'trimisLa', ''),
        );
    }
}
