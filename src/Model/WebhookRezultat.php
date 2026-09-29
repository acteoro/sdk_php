<?php

declare(strict_types=1);

namespace VerificRca\Model;

use VerificRca\Enum\StatusWebhook;
use VerificRca\Enum\TipVerificare;
use VerificRca\Internal\Decodor;

/** Payloadul POST trimis la `callbackUrl` cand o verificare se termina. */
final class WebhookRezultat
{
    /**
     * @param string $requestId acelasi `requestId` primit de la `porneste()`
     * @param TipVerificare $tip tipul verificarii
     * @param string $query identificatorul dupa care s-a cautat
     * @param string|null $serieSasiu seria de sasiu trimisa in cerere, daca exista
     * @param StatusWebhook $status starea documentului sau `Error` cand sursa oficiala nu a raspuns
     * @param VerificareFinala|null $result rezultatul complet, null la `Error`
     * @param \DateTimeImmutable|null $checkedAt cand a fost interogata sursa, null la eroare
     * @param string|null $error mesajul de eroare, doar la `Error`
     */
    public function __construct(
        public readonly string $requestId,
        public readonly TipVerificare $tip,
        public readonly string $query,
        public readonly ?string $serieSasiu,
        public readonly StatusWebhook $status,
        public readonly ?VerificareFinala $result,
        public readonly ?\DateTimeImmutable $checkedAt,
        public readonly ?string $error = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d): self
    {
        if (!array_key_exists('result', $d)) {
            Decodor::obiect($d, 'result', ''); // arunca "result: lipseste"
        }
        $result = $d['result'] === null ? null : VerificareFinala::fromArray(Decodor::obiect($d, 'result', ''), 'result');

        return new self(
            requestId: Decodor::sir($d, 'requestId', ''),
            tip: Decodor::enum($d, 'tip', '', TipVerificare::class),
            query: Decodor::sir($d, 'query', ''),
            serieSasiu: Decodor::sirSauNull($d, 'serieSasiu', ''),
            status: Decodor::enum($d, 'status', '', StatusWebhook::class),
            result: $result,
            checkedAt: Decodor::dataSauNull($d, 'checkedAt', ''),
            error: Decodor::sirOptional($d, 'error', ''),
        );
    }
}
