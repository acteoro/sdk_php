<?php

declare(strict_types=1);

// Server minim care primeste ambele tipuri de webhook, fara framework.
// Rulare: VERIFICRCA_WEBHOOK_KEY=whsec_... php -S 0.0.0.0:3000 examples/webhook-server.php

require __DIR__ . '/../vendor/autoload.php';

use Acteo\Exception\EroareWebhook;
use Acteo\Model\WebhookDocumentExpira;
use Acteo\Webhooks;

$corp = (string) file_get_contents('php://input');
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$cale = (string) parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);

try {
    if ($cale === '/verificrca/rezultat-9f3k2') {
        $r = Webhooks::parseazaWebhookRezultat($corp);
        error_log("Rezultat {$r->requestId}: {$r->tip->value} {$r->query} = {$r->status->value}");
    } elseif ($cale === '/verificrca/notificari') {
        $n = Webhooks::verificaWebhookNotificare($corp, $_SERVER, (string) getenv('VERIFICRCA_WEBHOOK_KEY'));
        if ($n instanceof WebhookDocumentExpira) {
            error_log("{$n->vehicul->numarInmatriculare}: {$n->document->value} expira in {$n->zileRamase} zile");
        }
    }
    http_response_code(200);
} catch (EroareWebhook) {
    http_response_code(400);
}
