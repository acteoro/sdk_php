<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Exception\EroareWebhook;
use Acteo\Model\WebhookDocumentExpira;
use Acteo\Model\WebhookRezultat;
use Acteo\Model\WebhookTest;
use Psr\Http\Message\ServerRequestInterface;

/** Citirea webhook-urilor trimise de API. Nu face cereri pe retea. Il accesezi prin `$acteo->webhooks`. */
final class ClientWebhooks
{
    /** @internal */
    public function __construct() {}

    /**
     * Citeste rezultatul trimis la `callbackUrl` dupa o verificare.
     *
     * Foloseste un `callbackUrl` pe HTTPS, greu de ghicit (de exemplu cu un token
     * secret in cale), si potriveste `requestId` cu cel primit la `porneste()`
     * inainte sa ai incredere in continut.
     *
     * @param string|array<mixed> $corp corpul cererii (`file_get_contents('php://input')`) sau JSON-ul deja decodat
     *
     * @throws EroareWebhook corpul nu e JSON (JSON_INVALID) sau nu e un rezultat valid (PAYLOAD_INVALID)
     */
    public function parseazaRezultat(string|array $corp): WebhookRezultat
    {
        return Webhooks::parseazaWebhookRezultat($corp);
    }

    /**
     * Verifica cheia si citeste o notificare de expirare (`document.expira`) sau
     * un test (`webhook.test`) trimise la URL-ul de webhook al contului.
     *
     * @param string|array<mixed> $corp corpul cererii sau JSON-ul deja decodat
     * @param array<mixed>|ServerRequestInterface $headere `getallheaders()`, `$_SERVER`,
     *   `$request->headers->all()` din Laravel/Symfony sau o cerere PSR-7
     * @param string $cheie cheia de webhook din cont (`whsec_...`)
     *
     * @throws EroareWebhook cheie lipsa, gresita sau corp invalid
     */
    public function verificaNotificare(string|array $corp, array|ServerRequestInterface $headere, string $cheie): WebhookDocumentExpira|WebhookTest
    {
        return Webhooks::verificaWebhookNotificare($corp, $headere, $cheie);
    }
}
